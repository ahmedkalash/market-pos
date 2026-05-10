<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PurchaseInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user()->load('company');

        return $schema->components([
            Section::make(__('purchase_invoice.purchase_invoice'))
                ->icon('heroicon-o-document-arrow-down')
                ->schema([
                    Select::make('vendor_id')
                        ->label(__('purchase_invoice.vendor'))
                        ->relationship('vendor', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Select::make('store_id')
                        ->label(__('purchase_invoice.store'))
                        ->options(fn (): array => Store::query()
                            ->filterByCompany($user->company_id)
                            ->pluck('name_'.app()->getLocale(), 'id')
                            ->toArray()
                        )
                        ->default(fn (): ?int => $user->store_id)
                        ->required()
                        ->searchable()
                        ->live()
                        ->visible(fn () => $user->isCompanyLevel()),

                    Hidden::make('store_id')
                        ->default(fn () => $user->store_id)
                        ->visible(fn () => $user->isStoreLevel()),

                    DatePicker::make('received_at')
                        ->label(__('purchase_invoice.received_at'))
                        ->required()
                        ->default(now()->toDateString())
                        ->maxDate(now()),

                    TextInput::make('vendor_invoice_ref')
                        ->label(__('purchase_invoice.vendor_invoice_ref'))
                        ->maxLength(100)
                        ->placeholder('INV-2024-0099'),
                ])->columns(2),

            Section::make(__('purchase_invoice.notes'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->schema([
                    Textarea::make('notes')
                        ->label(__('purchase_invoice.notes'))
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make(__('purchase_invoice.items'))
                ->icon('heroicon-o-shopping-cart')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->compact()
                        ->schema([
                            Hidden::make('product_variant_id')
                                ->required(),

                            TextInput::make('barcode')
                                ->label(__('purchase_invoice.barcode'))
                                ->dehydrated(false)
                                ->live(debounce: '500ms')
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    if (! $state) {
                                        $set('product_variant_id', null);
                                        $set('product_name', null);

                                        return;
                                    }

                                    // Duplicate guard: check if this barcode is already used in another line
                                    $allItems = $get('../../items') ?? [];
                                    $currentVariantId = $get('product_variant_id');
                                    $barcodeRecord = ProductBarcode::where('barcode', $state)->first();

                                    if (! $barcodeRecord) {
                                        $set('product_variant_id', null);
                                        $set('product_name', __('purchase_invoice.product_not_found'));

                                        return;
                                    }

                                    // Check store boundary
                                    $storeId = $get('../../store_id');
                                    $variant = ProductVariant::with('product.taxClass')
                                        ->find($barcodeRecord->product_variant_id);

                                    if (! $variant) {
                                        $set('product_variant_id', null);
                                        $set('product_name', __('purchase_invoice.product_not_found'));

                                        return;
                                    }

                                    // Validate variant belongs to the selected store
                                    if ($storeId && $variant->product->store_id !== (int) $storeId) {
                                        $set('product_variant_id', null);
                                        $set('product_name', __('purchase_invoice.product_wrong_store'));

                                        return;
                                    }

                                    // Check for duplicate variant in other lines
                                    foreach ($allItems as $itemKey => $item) {
                                        $existingVariantId = $item['product_variant_id'] ?? null;
                                        if ($existingVariantId && (int) $existingVariantId === $variant->id && (int) $existingVariantId !== (int) $currentVariantId) {
                                            $set('product_variant_id', null);
                                            $set('product_name', __('purchase_invoice.duplicate_barcode'));

                                            return;
                                        }
                                    }

                                    $set('product_variant_id', $variant->id);
                                    $set('product_name', "{$variant->product->name_ar} | {$variant->product->name_en}");
                                    $set('unit_cost', (float) $variant->purchase_price);

                                    // TAX FEATURE POSTPONED: Force tax rate to 0 for MVP
                                    // $set('tax_rate', (float) ($variant->product->taxClass?->rate ?? 0));
                                    $set('tax_rate', 0.0);

                                    self::recalculateLine($get, $set);
                                })
                                ->afterStateHydrated(function ($state, Set $set, Get $get): void {
                                    // On edit: populate barcode from the existing variant's first barcode
                                    $variantId = $get('product_variant_id');
                                    if (! $state && $variantId) {
                                        $barcode = ProductBarcode::where('product_variant_id', $variantId)->value('barcode');
                                        $set('barcode', $barcode);
                                    }
                                })
                                ->columnSpan(2),

                            TextInput::make('product_name')
                                ->label(__('purchase_invoice.product_name'))
                                ->dehydrated(false)
                                ->disabled()
                                ->readOnly()
                                ->afterStateHydrated(function ($state, Set $set, Get $get): void {
                                    // On edit: populate product name from the existing variant
                                    $variantId = $get('product_variant_id');
                                    if (! $state && $variantId) {
                                        $variant = ProductVariant::with('product')->find($variantId);
                                        if ($variant) {
                                            $set('product_name', "{$variant->product->name_ar} | {$variant->product->name_en}");
                                        }
                                    }
                                })
                                ->columnSpan(2),

                            TextInput::make('quantity')
                                ->label(__('purchase_invoice.quantity'))
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->minValue(0.001)
                                ->step(0.001)
                                ->live(debounce: '500ms')
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateLine($get, $set))
                                ->columnSpan(1),

                            TextInput::make('unit_cost')
                                ->label(__('purchase_invoice.unit_cost'))
                                ->numeric()
                                ->required()
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->minValue(0)
                                ->step(0.0001)
                                ->live(debounce: '500ms')
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateLine($get, $set))
                                ->helperText(__('purchase_invoice.unit_cost_helper'))
                                ->columnSpan(2),

                            TextInput::make('tax_rate')
                                ->label(__('purchase_invoice.tax_rate'))
                                ->readOnly()
                                ->suffix('%')
                                ->hidden() // TAX FEATURE POSTPONED
                                ->columnSpan(1),

                            TextInput::make('subtotal')
                                ->label(__('purchase_invoice.subtotal'))
                                ->numeric()
                                ->readOnly()
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->hidden() // TAX FEATURE POSTPONED
                                ->columnSpan(2),

                            TextInput::make('tax_amount')
                                ->label(__('purchase_invoice.tax_amount'))
                                ->readOnly()
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->hidden() // TAX FEATURE POSTPONED
                                ->columnSpan(2),

                            TextInput::make('line_total')
                                ->label(__('purchase_invoice.line_total'))
                                ->numeric()
                                ->readOnly()
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->columnSpan(2),

                            Textarea::make('notes')
                                ->label(__('purchase_invoice.item_notes'))
                                ->maxLength(255)
                                ->columnSpan(2),
                        ])
                        ->columns(8)
                        ->addActionLabel(__('purchase_invoice.item').' +')
                        ->reorderable(false)
                        ->cloneable(false)
                        ->defaultItems(1),
                ]),
        ]);
    }

    /**
     * Recalculate subtotal, tax_amount, and line_total when qty or unit_cost changes.
     */
    private static function recalculateLine(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitCost = (float) ($get('unit_cost') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);

        $subtotal = round($quantity * $unitCost, 2);
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $lineTotal = round($subtotal + $taxAmount, 2);

        $set('subtotal', $subtotal);
        $set('tax_amount', $taxAmount);
        $set('line_total', $lineTotal);
    }
}
