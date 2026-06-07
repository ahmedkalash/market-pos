<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
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
            Fieldset::make('global_disable')
                ->hiddenLabel()
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'class' => 'contents',
                ])
                ->columnSpanFull()
                ->schema([
                    Section::make(__('purchase_invoice.purchase_invoice'))
                        ->compact()
                        ->icon('heroicon-o-document-arrow-down')
                        ->columnSpanFull()
                        ->columns(fn() => $user->isCompanyLevel() ? 5 : 4)
                        ->schema([
                            static::getStoreIDInput($user),
                            Select::make('vendor_id')
                                ->label(__('purchase_invoice.vendor'))
                                ->relationship('vendor', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable(),
                            DatePicker::make('received_at')
                                ->label(__('purchase_invoice.received_at'))
                                ->required()
                                ->displayFormat('d/m/Y')
                                ->default(now()->toDateString())
                                ->maxDate(now()),
                            TextInput::make('vendor_invoice_ref')
                                ->label(__('purchase_invoice.vendor_invoice_ref'))
                                ->maxLength(100),
                            Textarea::make('notes')
                                ->label(__('purchase_invoice.notes'))
                                ->rows(1),
                        ]),

                    Section::make(__('purchase_invoice.items'))
                        ->compact()
                        ->icon('heroicon-o-shopping-cart')
                        ->columnSpanFull()
                        ->columns(2)
                        ->schema([
                            TextInput::make('barcode_scanner')
                                ->columnSpan(1)
                                ->label(__('purchase_invoice.barcode_scanner'))
                                ->placeholder(__('purchase_invoice.scan_barcode'))
                                ->helperText(__('purchase_invoice.barcode_scanner_helper'))
                                ->hiddenOn('view')
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
                                    if (!$state) {
                                        return;
                                    }
                                    $set('barcode_scanner', null);

                                    $barcodeRecord = ProductBarcode::query()->where('barcode', $state)->first();
                                    if (!$barcodeRecord) {
                                        Notification::make()->warning()->title(__('purchase_invoice.product_not_found'))->send();
                                        $livewire->dispatch('play-sound-error');
                                        $livewire->dispatch('focus-barcode');

                                        return;
                                    }

                                    $variant = ProductVariant::with(['product.taxClass', 'barcodes'])
                                        ->find($barcodeRecord->product_variant_id);

                                    if (!$variant) {
                                        Notification::make()->warning()->title(__('purchase_invoice.product_not_found'))->send();
                                        $livewire->dispatch('play-sound-error');
                                        $livewire->dispatch('focus-barcode');

                                        return;
                                    }

                                    self::addVariantToInvoice($variant, $get, $set, $livewire);

                                }),

                            Select::make('product_search')
                                ->columnSpan(1)
                                ->label(__('purchase_invoice.search_by_name'))
                                ->placeholder(__('purchase_invoice.search_by_name_placeholder'))
                                ->helperText(__('purchase_invoice.search_by_name_helper'))
                                ->helperText(__('purchase_invoice.search_by_name_helper', ['max' => 30]))
                                ->hiddenOn('view')
                                ->searchable()
                                ->allowHtml()
                                ->options([])
                                ->getSearchResultsUsing(function (string $search, $get) {
                                    if (blank($search)) {
                                        return [];
                                    }

                                    return ProductVariant::query()
                                        ->filterByStore($get('store_id'))
                                        ->with('product')
                                        ->fullNameSearch($search)
                                        ->limit(30)
                                        ->get()
                                        ->mapWithKeys(function ($variant) {
                                            $barcodesText = ($barcodes = $variant->getAllBarcodesAsString()) ? badge($barcodes) : '';
                                            $fullName = badge($variant->full_qualified_name);

                                            return [$variant->id => "<div class='flex flex-wrap items-center gap-2' dir='auto'>$fullName $barcodesText</div>"];
                                        })
                                        ->toArray();
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) {
                                    if (!$state) {
                                        return;
                                    }

                                    // Clear the select field so it can be used again
                                    $set('product_search', null);

                                    $variant = ProductVariant::with(['product.taxClass', 'barcodes'])
                                        ->find($state);

                                    if (!$variant) {
                                        Notification::make()->warning()->title(__('purchase_invoice.product_not_found'))->send();
                                        $livewire->dispatch('play-sound-error');

                                        return;
                                    }

                                    self::addVariantToInvoice($variant, $get, $set, $livewire);
                                }),

                            Repeater::make('items')
                                ->columnSpanFull()
                                ->relationship()
                                ->mutateRelationshipDataBeforeFillUsing(function (array $data, Model $record): array {
                                    // todo: optimize by eager loading variants for all items in the form instead of querying for each item here
                                    $variant = ProductVariant::with(['product', 'barcodes'])->find($data['product_variant_id'] ?? null);

                                    if ($variant) {
                                        $data['product_name'] = $variant->full_qualified_name;
                                        $data['barcodes'] = $variant->getAllBarcodesAsArray();
                                    }

                                    return $data;
                                })
                                ->itemLabel(function (array $state): ?HtmlString {
                                    $barcodes = $state['barcodes'] ?? [];
                                    $productHtml = badge($state['product_name'] ?? __('app.unknown_product'));

                                    if (empty($barcodes)) {
                                        return new HtmlString("<div class='flex items-center'>$productHtml</div>");
                                    }

                                    $badgesHtml = collect($barcodes)->map(function ($barcode) {
                                        return badge($barcode);
                                    })->implode(' ');

                                    return new HtmlString("<div class='flex items-center'>$productHtml<span class='text-sm text-gray-500' style='margin-inline-end: 0.5rem;'>".__('sale_return.barcode').":</span>$badgesHtml</div>");
                                })
                                ->hiddenLabel()
                                ->compact()
                                ->schema([
                                    Hidden::make('product_variant_id')->required(),

                                    TextInput::make('quantity')
                                        ->label(__('purchase_invoice.quantity'))
                                        ->numeric()
                                        ->required()
                                        ->default(1)
                                        ->minValue(0.001)
                                        ->step(0.001)
                                        ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.quantity_tooltip'))
                                        ->disabled(fn(Get $get) => !$get('product_variant_id'))
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::recalculateLine($get, $set);
                                            self::calcTotalAmount($get, $set, '../../');
                                        })
                                        ->columnSpan(4),
                                    //  (ex. Tax)
                                    TextInput::make('unit_cost')
                                        ->label(__('purchase_invoice.unit_cost'))
                                        ->numeric()
                                        ->required()
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.unit_cost_tooltip'))
                                        ->disabled(fn(Get $get) => !$get('product_variant_id'))
                                        ->live(debounce: '500ms')
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::recalculateLine($get, $set);
                                            self::calcTotalAmount($get, $set, '../../');
                                        })
                                        ->columnSpan(4),
                                    TextInput::make('line_total')
                                        ->label(__('purchase_invoice.line_total'))
                                        ->numeric()
                                        ->readOnly()
                                        ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.line_total_tooltip'))
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->columnSpan(4),
                                    Textarea::make('notes')
                                        ->label(__('purchase_invoice.item_notes'))
                                        ->maxLength(255)
                                        ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.notes_tooltip'))
                                        ->rows(1)
                                        ->columnSpanFull(),
                                ])
                                ->columns(12)
                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false)
                                ->defaultItems(0)
                                ->deleteAction(
                                    fn($action) => $action->after(fn(Get $get, Set $set) => self::calcTotalAmount($get, $set))
                                ),

                        ]),

                    Section::make(__('app.summary'))
                        ->compact()
                        ->columnSpanFull()
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Grid::make(1)
                                ->columnSpanFull()
                                ->schema([
                                    TextInput::make('total_amount')
                                        ->columnSpanFull()
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
                        ]),
                ]),

        ]);
    }

    /**
     * Recalculate subtotal, tax_amount, and line_total when qty or unit_cost changes.
     */
    private static function recalculateLine(Get $get, Set $set): void
    {
        $quantity = (float)($get('quantity') ?? 0);
        $unitCost = (float)($get('unit_cost') ?? 0);
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
        $items = $get($prefix . 'items') ?? [];
        $total = collect($items)->sum('line_total');
        $set($prefix . 'total_amount', round($total, 2));
    }

    /**
     * Shared helper to add a variant to the invoice items list.
     * Prevents code duplication between barcode scanner and name search.
     */
    protected static function addVariantToInvoice(ProductVariant $variant, Get $get, Set $set, $livewire): void
    {
        // Check store boundary
        $storeId = $get('store_id');

        // Validate variant belongs to the selected store
        if ($storeId && $variant->product->store_id !== (int)$storeId) {
            Notification::make()->warning()->title(__('purchase_invoice.product_wrong_store'))->send();
            $livewire->dispatch('play-sound-error');
            $livewire->dispatch('focus-barcode');

            return;
        }

        $items = $get('items') ?? [];
        $newKey = 'item_' . $variant->id;

        // Check for duplicate variant in existing items (works for both UUID keys and scanned item keys)
        $alreadyExists = collect($items)->contains(
            fn($item) => ((int)($item['product_variant_id'] ?? 0)) === (int)$variant->id
        );

        if ($alreadyExists) {
            Notification::make()->warning()->title(__('purchase_invoice.duplicate_barcode'))->send();
            $livewire->dispatch('play-sound-error');
            $livewire->dispatch('focus-barcode');

            return;
        }

        $fullName = $variant->full_qualified_name;
        $barcodes = $variant->getAllBarcodesAsArray();
        $unitCost = (float)$variant->purchase_price;

        $items[$newKey] = [
            'product_variant_id' => $variant->id,
            'barcodes' => $barcodes,
            'product_name' => $fullName,
            'quantity' => 1,
            'unit_cost' => $unitCost,
            'line_total' => round($unitCost, 2),
            'notes' => null,
        ];

        $set('items', $items);
        self::calcTotalAmount($get, $set);

        Notification::make()
            ->title(__('purchase_invoice.item_added'))
            ->body($fullName)
            ->success()
            ->send();

        $livewire->dispatch('play-sound-success');
        $livewire->dispatch('focus-barcode');
    }

    private static function getStoreIDInput(User $user)
    {
        if ($user->isStoreLevel()) {
            return Hidden::make('store_id')
                ->required()
                ->default(fn() => $user->store_id)
                ->disabled(fn(string $operation): bool => $operation === 'edit')
                ->dehydrated(fn(string $operation): bool => $operation !== 'edit');
        }
        return Select::make('store_id')
            ->label(__('purchase_invoice.store'))
            ->relationship('store', lang_suffix('name'))
            ->preload()
            ->required()
            ->searchable(['name_en', 'name_ar'])
            ->live()
            ->visible(fn() => $user->isCompanyLevel())
            ->disabled(fn(string $operation): bool => $operation === 'edit')
            ->dehydrated(fn(string $operation): bool => $operation !== 'edit');
    }
}
