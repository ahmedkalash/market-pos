<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

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
                    TextInput::make('barcode_scanner')
                        ->label(__('purchase_invoice.barcode_scanner'))
                        ->placeholder(__('purchase_invoice.scan_barcode'))
                        ->helperText(__('purchase_invoice.barcode_scanner_helper'))
                        ->autofocus()
                        ->extraInputAttributes([
                            'x-on:focus-barcode.window' => 'setTimeout(() => $el.focus(), 10)',
                        ])
                        ->live(onBlur: true)
                        ->prefixAction(
                            Action::make('search')
                                ->icon('heroicon-m-magnifying-glass')
                                ->label(__('purchase_invoice.search'))
                        )
                        ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) {
                            if (! $state) {
                                return;
                            }
                            $set('barcode_scanner', null);

                            $barcodeRecord = ProductBarcode::where('barcode', $state)->first();
                            if (! $barcodeRecord) {
                                Notification::make()->warning()->title(__('purchase_invoice.product_not_found'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            // Check store boundary
                            $storeId = $get('store_id');
                            $variant = ProductVariant::with(['product.taxClass', 'barcodes'])
                                ->find($barcodeRecord->product_variant_id);

                            if (! $variant) {
                                Notification::make()->warning()->title(__('purchase_invoice.product_not_found'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            // Validate variant belongs to the selected store
                            if ($storeId && $variant->product->store_id !== (int) $storeId) {
                                Notification::make()->warning()->title(__('purchase_invoice.product_wrong_store'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            $items = $get('items') ?? [];
                            $newKey = 'item_'.$variant->id;

                            // Check for duplicate variant in existing items (works for both UUID keys and scanned item keys)
                            $alreadyExists = collect($items)->contains(
                                fn ($item) => ((int) ($item['product_variant_id'] ?? 0)) === (int) $variant->id
                            );

                            if ($alreadyExists) {
                                Notification::make()->warning()->title(__('purchase_invoice.duplicate_barcode'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            $locale = app()->getLocale();
                            $productName = $variant->product->{"name_$locale"} ?? '';
                            $variantName = $variant->{"name_$locale"} ?? '';
                            $fullName = $variantName ? "{$productName} - {$variantName}" : $productName;

                            $barcodes = $variant->barcodes->pluck('barcode')->toArray();

                            $unitCost = (float) $variant->purchase_price;

                            $items[$newKey] = [
                                'product_variant_id' => $variant->id,
                                'barcodes' => $barcodes,
                                'product_name' => $fullName,
                                'quantity' => 1,
                                'unit_cost' => $unitCost,
                                'line_total' => round(1 * $unitCost, 2),
                                'notes' => null,
                            ];

                            $set('items', $items);
                            self::calcTotalAmount($get, $set);

                            $livewire->dispatch('play-sound-success');
                            $livewire->dispatch('focus-barcode');
                        }),

                    Repeater::make('items')
                        ->relationship()
                        ->mutateRelationshipDataBeforeFillUsing(function (array $data, Model $record): array {
                            $variant = ProductVariant::with(['product', 'barcodes'])->find($data['product_variant_id'] ?? null);
                            $locale = app()->getLocale();

                            if ($variant) {
                                $productName = $variant->product?->{"name_{$locale}"} ?? '';
                                $variantName = $variant->{"name_{$locale}"} ?? '';
                                $data['product_name'] = $variantName ? "{$productName} - {$variantName}" : $productName;
                                $data['barcodes'] = $variant->barcodes->pluck('barcode')->toArray();
                            }

                            return $data;
                        })
                        ->itemLabel(function (array $state): ?HtmlString {
                            $barcodes = $state['barcodes'] ?? [];

                            if (empty($barcodes)) {
                                return null;
                            }

                            $badges = collect($barcodes)->map(function ($barcode) {
                                return "<span style='margin-inline-end: 0.5rem;' class='inline-flex items-center justify-center min-h-6 px-2 py-0.5 text-sm font-medium tracking-tight rounded-xl text-primary-700 bg-primary-50 ring-1 ring-inset ring-primary-600/10 dark:text-primary-400 dark:bg-primary-400/10 dark:ring-primary-400/30'>{$barcode}</span>";
                            })->implode('');

                            return new HtmlString("<div class='flex items-center'>".__('purchase_invoice.barcode').': '.$badges.'</div>');
                        })
                        ->hiddenLabel()
                        ->compact()
                        ->schema([
                            Hidden::make('product_variant_id')
                                ->required(),

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
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.quantity_tooltip'))
                                ->disabled(fn (Get $get) => ! $get('product_variant_id'))
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::recalculateLine($get, $set);
                                    self::calcTotalAmount($get, $set, '../../');
                                })
                                ->columnSpan(1),

                            //  (ex. Tax)
                            TextInput::make('unit_cost')
                                ->label(__('purchase_invoice.unit_cost'))
                                ->numeric()
                                ->required()
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->minValue(0)
                                ->step(0.01)
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.unit_cost_tooltip'))
                                ->disabled(fn (Get $get) => ! $get('product_variant_id'))
                                ->live(debounce: '500ms')
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::recalculateLine($get, $set);
                                    self::calcTotalAmount($get, $set, '../../');
                                })
                                ->columnSpan(2),

                            // TAX FEATURE POSTPONED
                            //                            TextInput::make('tax_rate')
                            //                                ->label(__('purchase_invoice.tax_rate'))
                            //                                ->readOnly()
                            //                                ->suffix('%')
                            //                                ->hidden() // TAX FEATURE POSTPONED
                            //                                ->columnSpan(1),

                            // TAX FEATURE POSTPONED
                            //                            TextInput::make('subtotal')
                            //                                ->label(__('purchase_invoice.subtotal'))
                            //                                ->numeric()
                            //                                ->readOnly()
                            //                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                            //                                ->hidden() // TAX FEATURE POSTPONED
                            //                                ->columnSpan(2),

                            // TAX FEATURE POSTPONED
                            //                            TextInput::make('tax_amount')
                            //                                ->label(__('purchase_invoice.tax_amount'))
                            //                                ->readOnly()
                            //                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                            //                                ->hidden() // TAX FEATURE POSTPONED
                            //                                ->columnSpan(2),

                            TextInput::make('line_total')
                                ->label(__('purchase_invoice.line_total'))
                                ->numeric()
                                ->readOnly()
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.line_total_tooltip'))
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->columnSpan(2),

                            Textarea::make('notes')
                                ->label(__('purchase_invoice.item_notes'))
                                ->maxLength(255)
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.notes_tooltip'))
                                ->columnSpan(2),
                        ])
                        ->columns(8)
                        ->addable(false)
                        ->reorderable(false)
                        ->cloneable(false)
                        ->defaultItems(0)
                        ->deleteAction(
                            fn ($action) => $action->after(fn (Get $get, Set $set) => self::calcTotalAmount($get, $set))
                        ),

                    TextInput::make('total_amount')
                        ->label(__('purchase_invoice.total_amount'))
                        ->disabled()
                        ->dehydrated(false)
                        ->extraInputAttributes(['class' => 'text-xl font-bold'])
                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            static::calcTotalAmount($get, $set);
                        })
                        ->columnSpanFull(),
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
        //        $taxRate = (float) ($get('tax_rate') ?? 0); // TAX FEATURE POSTPONED

        $subtotal = round($quantity * $unitCost, 2);
        //        $taxAmount = round($subtotal * $taxRate / 100, 2); // TAX FEATURE POSTPONED
        $taxAmount = 0.0; // TAX FEATURE POSTPONED
        $lineTotal = round($subtotal + $taxAmount, 2);

        //        $set('subtotal', $subtotal);
        //        $set('tax_amount', $taxAmount); // TAX FEATURE POSTPONED
        $set('line_total', $lineTotal);
    }

    private static function calcTotalAmount(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix.'items') ?? [];
        $total = collect($items)->sum('line_total');
        $set($prefix.'total_amount', round($total, 2));
    }
}
