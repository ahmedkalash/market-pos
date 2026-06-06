<?php

namespace App\Filament\Resources\SaleReturnInvoices\Schemas;

use App\Enums\ExtraItemActionType;
use App\Enums\InvoiceType;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\User;
use App\Services\ExtraItemPresetCache;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Class SaleReturnInvoiceForm
 *
 * This class is responsible for defining the Filament form schema for creating and editing Sale Return Invoices.
 * It handles the user interface for selecting an original sale invoice, searching for products to return,
 * dynamically populating the return items based on the original invoice data, managing quantities and refunds,
 * and applying extra items (like addition or subtraction presets) to calculate the final refund amounts.
 */
class SaleReturnInvoiceForm
{
    /**
     * Configure the Filament form schema for the Sale Return Invoice.
     *
     * This method builds the complete form layout consisting of three main sections:
     * 1. Sale Return (Invoice metadata, original invoice lookup, customer and store data, etc.)
     * 2. Items (The items being returned, quantities, unit prices, and refund amounts)
     * 3. Extra Items (Additional adjustments to the refund, such as restocking fees or extra charges)
     *
     * @param  Schema  $schema  The initial schema instance provided by Filament.
     * @return Schema The fully configured schema containing all form components.
     */
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user()->load('company');

        // Dedicated cache object — request-scoped, Octane-safe, zero DB duplication.
        $extraItemPresetCache = new ExtraItemPresetCache;

        return $schema
            ->components([
                // todo use field set and disable all form on wire load event to prevent overwriting
                //  the form data in the browser by the one coming back from the server
                Section::make(__('app.sale_return'))
                    ->compact()
                    ->columnSpanFull()
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->columns(4)
                    ->schema([
                        TextInput::make('invoice_number_input')
                            ->label(__('app.original_invoice_id'))
                            ->helperText(__('sale_return.invoice_search_helper'))
                            ->prefixAction(
                                Action::make('search')
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->label(__('purchase_return.search'))
                            )
                            ->required()
                            ->default(function () {
                                $invoiceId = self::getOriginalInvoiceIdFromRequest();

                                return self::getCachedOriginalInvoice($invoiceId)?->invoice_number;
                            })
                            ->live(onBlur: true)
                            ->stateBindingModifiers(['blur', 'trim'])
                            ->afterStateUpdated(function ($state, Get $get, Set $set, $livewire) {
                                $set('items', []); // clear items
                                $set('extraItems', []); // clear extra items
                                self::clearOriginalInvoiceMetadata($set);
                                self::calcTotalAmount($get, $set, $livewire);

                                if ($state) {
                                    $invoice = SaleInvoice::query()
                                        ->returnable()
                                        ->where('invoice_number', $state)
                                        ->first();

                                    if ($invoice) {
                                        self::hydrateOriginalInvoiceMetadata($set, $invoice);
                                        Notification::make()->success()->title(__('sale_return.invoice_found_success'))->send();
                                        $livewire->dispatch('play-sound-success');
                                    } else {
                                        Notification::make()->warning()->title(__('sale_return.invoice_not_found'))->send();
                                        $livewire->dispatch('play-sound-error');
                                    }
                                }
                            })
                            ->formatStateUsing(fn ($state, $record) => $record ? $record->originalInvoice?->invoice_number : $state)
                            ->dehydrated(false)
                            ->columnSpan(1),

                        Hidden::make('original_invoice_id')
                            ->default(self::getOriginalInvoiceIdFromRequest())
                            ->required(),

                        Select::make('customer_id')
                            ->native(false)
                            ->columnSpan(1)
                            ->label(__('app.customer'))
                            ->relationship('customer', 'name')
                            ->default(function () {
                                $invoiceId = self::getOriginalInvoiceIdFromRequest();

                                return self::getCachedOriginalInvoice($invoiceId)?->customer_id;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText(__('sale_return.customer_helper')),

                        Select::make('store_id')
                            ->native(false)
                            ->columnSpan(1)
                            ->label(__('app.store'))
                            ->relationship('store', lang_suffix('name'))
                            ->preload()
                            ->default(function () {
                                $invoiceId = self::getOriginalInvoiceIdFromRequest();

                                return self::getCachedOriginalInvoice($invoiceId)?->store_id;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText(__('sale_return.store_helper'))
                            ->visible(fn () => $user->isCompanyLevel()),

                        Hidden::make('store_id')
                            ->required()
                            ->default(function () {
                                $invoiceId = self::getOriginalInvoiceIdFromRequest();

                                return self::getCachedOriginalInvoice($invoiceId)?->store_id;
                            })
                            ->visible(fn () => $user->isStoreLevel()),

                        DatePicker::make('returned_at')
                            ->columnSpan(1)
                            ->label(__('app.returned_at'))
                            ->helperText(__('sale_return.returned_at_helper'))
                            ->required()
                            ->default(now())
                            ->displayFormat('d/m/Y')
                            ->maxDate(now())
                            ->native(false),

                        Textarea::make('return_reason')
                            ->label(__('app.return_reason'))
                            ->helperText(__('sale_return.return_reason_helper'))
                            ->required()
                            ->rows(1)
                            ->columnSpan(2),

                        Textarea::make('notes')
                            ->columnSpan(2)
                            ->label(__('app.notes'))
                            ->rows(1)
                            ->helperText(__('sale_return.notes_helper')),

                    ]),

                Section::make(__('app.items'))
                    ->compact()
                    ->icon('heroicon-o-shopping-cart')
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => filled($get('original_invoice_id')))
                    ->headerActions([
                        Action::make('add_all_items')
                            ->label(__('sale_return.return_all_items'))
                            ->icon('heroicon-o-bars-arrow-down')
                            ->color('primary')
                            ->action(function (Get $get, Set $set, $livewire) {
                                $invoiceId = $get('original_invoice_id');
                                if (! $invoiceId) {
                                    return;
                                }

                                $invoice = SaleInvoice::with(['items.variant.product', 'items.variant.barcodes'])->find($invoiceId);
                                if (! $invoice) {
                                    return;
                                }

                                $items = static::getAllInvoiceItemsForReturn($invoice);
                                $set('items', $items);
                                static::calcTotalAmount($get, $set, $livewire);
                            })
                            ->visible(
                                fn (Get $get, $livewire) => filled($get('original_invoice_id')) &&
                                    ! ($livewire instanceof ViewRecord)
                            ),
                    ])
                    ->schema([
                        Select::make('product_search')
                            ->allowHtml()
                            ->label(__('sale_return.search_by_product'))
                            ->placeholder(__('sale_return.search_by_product_placeholder'))
                            ->helperText(__('sale_return.search_by_product_helper'))
                            ->visible(
                                fn (Get $get, $livewire) => filled($get('original_invoice_id')) &&
                                    ! ($livewire instanceof ViewRecord)
                            )
                            ->options(function (Get $get) {
                                $invoiceId = $get('original_invoice_id');
                                if (! $invoiceId) {
                                    return [];
                                }

                                $items = SaleInvoiceItem::with(['variant.product', 'variant.barcodes'])
                                    ->where('sale_invoice_id', $invoiceId)
                                    ->get();

                                return $items->mapWithKeys(function (SaleInvoiceItem $item) {
                                    $fullName = badge($item->variant->full_qualified_name);
                                    $barcodes = $item->variant->getAllBarcodesAsString();
                                    $barcodeText = $barcodes ? badge($barcodes) : '';
                                    $max = $item->getRemainingReturnableQuantity();
                                    $maxLabel = badge(__('sale_return.max_suffix').$max);

                                    return [$item->id => "<div class='flex flex-wrap items-center gap-2' dir='auto'>$fullName $barcodeText $maxLabel</div>"];
                                })->toArray();
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set, $livewire) {
                                if (! $state) {
                                    return;
                                }
                                $set('product_search', null);

                                $originalItem = SaleInvoiceItem::with(['variant.product', 'variant.barcodes'])->find($state);
                                if (! $originalItem) {
                                    return;
                                }

                                static::addOriginalItemToReturn($originalItem, $get, $set, $livewire);
                            }),

                        Repeater::make('items')
                            ->relationship('items')
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data, Model $record): array {
                                $variant = ProductVariant::with(['product', 'barcodes'])->find($data['product_variant_id'] ?? null);

                                if ($variant) {
                                    $data['product_name'] = $variant->full_qualified_name;
                                    $data['barcodes'] = $variant->getAllBarcodesAsArray();
                                }

                                // TODO: Performance Optimization (N+1 Queries)
                                // To prevent N+1 queries here, do not save `max_returnable` to the DB (it risks stale data/over-returning).
                                // Instead, implement a static array cache to eager load all original items into memory once, and read from it.
                                $originalItem = SaleInvoiceItem::query()->find($data['original_item_id']);
                                if ($originalItem) {
                                    $data['max_returnable'] = $originalItem->getRemainingReturnableQuantity($record->id);
                                }

                                return $data;
                            })
                            ->hiddenLabel()
                            ->compact()
                            ->itemLabel(function ($state): ?HtmlString {
                                $barcodes = $state['barcodes'] ?? [];
                                $productHtml = badge(e($state['product_name'] ?? __('sale_return.unknown_product')));

                                if (empty($barcodes)) {
                                    return new HtmlString("<div class='flex items-center'>$productHtml</div>");
                                }

                                $badgesHtml = collect($barcodes)->map(function ($barcode) {
                                    return badge(e($barcode));
                                })->implode(' ');

                                return new HtmlString("<div class='flex items-center'>$productHtml<span class='text-sm text-gray-500' style='margin-inline-end: 0.5rem;'>".__('sale_return.barcode').":</span>$badgesHtml</div>");
                            })
                            ->deleteAction(
                                fn (Action $action) => $action->after(function (Get $get, Set $set, $livewire) {
                                    self::calcTotalAmount($get, $set, $livewire);
                                })
                            )
                            ->columnSpanFull()
                            ->schema([
                                Hidden::make('original_item_id')->required(),
                                Hidden::make('product_variant_id')->required(),
                                Hidden::make('max_returnable')->dehydrated(false),
                                Hidden::make('unit_discount_amount')->default(0),
                                Hidden::make('unit_prorated_global_discount')->default(0),
                                Hidden::make('unit_price')->required(),

                                TextInput::make('quantity')
                                    ->label(__('app.quantity'))
                                    ->helperText(__('sale_return.qty_tooltip'))
                                    ->hintIcon('heroicon-m-information-circle', tooltip: __('sale_return.quantity_tooltip'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->live(debounce: 1000)
                                    ->afterStateUpdated(function (Get $get, Set $set, $state, $livewire) {
                                        // validate quantity and set it to max_returnable if it exceeds that value.
                                        $max = (float) $get('max_returnable');
                                        if ((float) $state > $max) {
                                            $set('quantity', $max);
                                            Notification::make()->warning()->title(__('sale_return.exceeds_returnable_quantity', ['max' => $max]))->send();
                                        }
                                        self::calculateLine($get, $set);
                                        self::calcTotalAmount($get, $set, $livewire, '../../');
                                    })
                                    ->hint(fn (Get $get) => __('sale_return.max_suffix').' '.$get('max_returnable'))
                                    ->columnSpan(2),

                                TextInput::make('effective_unit_refund')
                                    ->label(__('sale_return.effective_unit_refund'))
                                    ->prefix($user->company->currency_symbol ?? 'ج.م')
                                    ->helperText(function (Get $get) {
                                        return __('sale_return.pricing_breakdown', [
                                            'price' => number_format($unit_price = (float) $get('unit_price'), 2),
                                            'unit_disc' => number_format($unit_discount_amount = (float) $get('unit_discount_amount'), 2),
                                            'global_disc' => number_format($unit_prorated_global_discount = (float) $get('unit_prorated_global_discount'), 2),
                                            'net' => number_format($unit_price - $unit_discount_amount - $unit_prorated_global_discount, 2),
                                        ]);
                                    })
                                    ->numeric()
                                    ->disabled(fn () => ! $user?->can('override_sale_return_refund_amount'))
                                    ->dehydrated()
                                    ->live(debounce: 1000)
                                    ->afterStateUpdated(function (Get $get, Set $set, $livewire) {
                                        self::calculateLine($get, $set);
                                        self::calcTotalAmount($get, $set, $livewire, '../../');
                                    })
                                    ->columnSpan(5),

                                TextInput::make('item_refund_total')
                                    ->label(__('sale_return.item_refund_total'))
                                    ->helperText(__('sale_return.line_total_tooltip'))
                                    ->prefix($user->company->currency_symbol ?? 'ج.م')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0.0)
                                    ->columnSpan(3),

                                Textarea::make('notes')
                                    ->label(__('app.notes'))
                                    ->helperText(__('sale_return.item_notes_tooltip'))
                                    ->rows(1)
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->addable(false)
                            ->reorderable(false)
                            ->cloneable(false)
                            ->defaultItems(0),
                    ]),

                Section::make(__('app.extra_items'))
                    ->compact()
                    ->icon('heroicon-o-plus-circle')
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => filled($get('original_invoice_id')))
                    ->schema([
                        Repeater::make('extraItems')
                            ->compact()
                            ->relationship('extraItems')
                            ->addActionLabel(__('app.add_more'))
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state) => $state['name'])
                            ->schema([
                                Select::make('invoice_extra_item_preset_id')
                                    ->label(__('app.preset'))
                                    ->dehydrated(false)
                                    ->live()
                                    ->options(fn () => $extraItemPresetCache->get(null, InvoiceType::SaleReturn)->pluck('name', 'id'))
                                    ->afterStateUpdated(function ($state, Get $get, Set $set, $livewire) use ($extraItemPresetCache) {
                                        if ($state) {
                                            $preset = $extraItemPresetCache->get((int) $state);
                                            if ($preset) {
                                                $set('name', $preset->name);
                                                $set('action_type', $preset->action_type);
                                                $set('amount', $preset->amount);
                                            }
                                        }
                                        self::calcTotalAmount($get, $set, $livewire, '../../');
                                    }),
                                TextInput::make('name')
                                    ->label(__('app.name'))
                                    ->helperText(__('sale_return.extra_name_tooltip'))
                                    ->required(),
                                Select::make('action_type')
                                    ->label(__('extra_item.action_type'))
                                    ->helperText(__('sale_return.extra_type_tooltip'))
                                    ->options(ExtraItemActionType::class)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $livewire) {
                                        self::calcTotalAmount($get, $set, $livewire, '../../');
                                    }),
                                TextInput::make('amount')
                                    ->label(__('app.amount'))
                                    ->helperText(__('sale_return.extra_amount_tooltip'))
                                    ->prefix($user->company->currency_symbol ?? 'ج.م')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->live(debounce: 1000)
                                    ->afterStateUpdated(function (Get $get, Set $set, $livewire) {
                                        self::calcTotalAmount($get, $set, $livewire, '../../');
                                    }),
                            ])
                            ->deleteAction(
                                fn (Action $action) => $action->after(function (Get $get, Set $set, $livewire) {
                                    self::calcTotalAmount($get, $set, $livewire);
                                })
                            )
                            ->columns(4),
                    ]),

                Section::make(__('app.summary'))
                    ->compact()
                    ->icon('heroicon-o-calculator')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('items_refund_total')
                            ->label(__('app.items_refund_total'))
                            ->helperText(__('sale_return.items_refund_helper'))
                            ->prefix($user->company->currency_symbol ?? 'ج.م')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->minValue(0)
                            ->default(0.0),
                        TextInput::make('extra_items_total')
                            ->label(__('app.extra_items_total'))
                            ->helperText(__('sale_return.extra_items_helper'))
                            ->prefix($user->company->currency_symbol ?? 'ج.م')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->default(0.0),
                        TextInput::make('total_refund_amount')
                            ->label(__('app.total_refund_amount'))
                            ->helperText(__('sale_return.total_refund_helper'))
                            ->prefix($user->company->currency_symbol ?? 'ج.م')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->default(0.0)
                            ->minValue(0)
                            ->extraInputAttributes(['class' => 'text-xl font-bold']),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * Add an item from the original sale invoice to the return form repeater.
     *
     * This method calculates the maximum returnable quantity for the selected original item.
     * If the item is already added or has no returnable quantity, it dispatches an error notification.
     * Otherwise, it constructs the new item array with its associated breakdown (unit price, discounts, net refund)
     * and appends it to the 'items' repeater state.
     *
     * @param  SaleInvoiceItem  $originalItem  The original invoice item to be added to the return.
     * @param  Get  $get  The Filament form state getter.
     * @param  Set  $set  The Filament form state setter.
     * @param  mixed  $livewire  The active Livewire component instance.
     */
    protected static function addOriginalItemToReturn(SaleInvoiceItem $originalItem, Get $get, Set $set, $livewire): void
    {
        $maxReturnable = $originalItem->getRemainingReturnableQuantity();
        if ($maxReturnable <= 0) {
            Notification::make()->warning()->title(__('sale_return.no_remaining_quantity'))->send();
            $livewire->dispatch('play-sound-error');
            $livewire->dispatch('focus-barcode');

            return;
        }

        $items = $get('items') ?? [];
        $key = 'item_'.$originalItem->id;

        $alreadyExists = collect($items)->contains(
            fn ($item) => ((int) ($item['original_item_id'] ?? 0)) === (int) $originalItem->id
        );

        if ($alreadyExists) {
            Notification::make()->warning()->title(__('sale_return.item_already_added'))->send();
            $livewire->dispatch('play-sound-error');
            $livewire->dispatch('focus-barcode');

            return;
        }

        $barcodes = $originalItem->variant->getAllBarcodesAsArray();
        $fullName = $originalItem->variant->full_qualified_name;
        // todo review this calculateRefundBreakdown fun
        $refundBreakdown = SaleInvoiceService::make()->calculateRefundBreakdown($originalItem);

        $items[$key] = [
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $originalItem->product_variant_id,
            'barcodes' => $barcodes,
            'product_name' => $fullName,
            'quantity' => 1,
            'max_returnable' => $maxReturnable,
            'unit_price' => (float) $originalItem->unit_price,
            'unit_discount_amount' => (float) $originalItem->monetary_unit_discount_amount,
            'unit_prorated_global_discount' => (float) $refundBreakdown['unit_prorated_global_discount'],
            'effective_unit_refund' => (float) $refundBreakdown['effective_unit_refund'],
            'item_refund_total' => round(1 * $refundBreakdown['effective_unit_refund'], 2),
            'notes' => null,
        ];

        $set('items', $items);
        self::calcTotalAmount($get, $set, $livewire);
        $livewire->dispatch('play-sound-success');
        $livewire->dispatch('focus-barcode');
    }

    /**
     * Hydrate the form state with metadata from the selected original sale invoice.
     *
     * Extracts relevant fields (e.g., invoice ID, customer ID, store ID) from the original
     * SaleInvoice model and populates the corresponding form fields.
     *
     * @param  Set  $set  The Filament form state setter.
     * @param  SaleInvoice|null  $invoice  The original sale invoice model, or null to clear metadata.
     */
    protected static function hydrateOriginalInvoiceMetadata(Set $set, ?SaleInvoice $invoice): void
    {
        $set('original_invoice_id', $invoice?->id);
        $set('customer_id', $invoice?->customer_id);
        $set('store_id', $invoice?->store_id);
    }

    /**
     * Clear the original invoice metadata from the form state.
     *
     * This is typically called when the user clears the invoice search input or enters an invalid invoice number,
     * ensuring that no stale customer or store data remains.
     *
     * @param  Set  $set  The Filament form state setter.
     */
    protected static function clearOriginalInvoiceMetadata(Set $set): void
    {
        self::hydrateOriginalInvoiceMetadata($set, null);
    }

    /**
     * Retrieve and validate the 'original_invoice_id' from the current HTTP request query parameters.
     *
     * Used to pre-fill the return form if the user navigates from a specific sale invoice to create a return.
     *
     * @return int|null The validated original invoice ID, or null if missing/invalid.
     */
    protected static function getOriginalInvoiceIdFromRequest(): ?int
    {
        return request()->integer('original_invoice_id') ?: null;
    }

    /**
     * Retrieve a cached SaleInvoice instance by its ID.
     *
     * Returns a cached SaleInvoice for the given ID to avoid repeated database queries
     * when populating default values across multiple fields (e.g., customer, store).
     *
     * @param  int|null  $invoiceId  The ID of the original sale invoice to fetch.
     * @return SaleInvoice|null The retrieved SaleInvoice model, or null if not found.
     */
    protected static function getCachedOriginalInvoice(?int $invoiceId): ?SaleInvoice
    {
        // todo: static $cache = [] will leak between requests under Octane consider using non-static property
        if (! $invoiceId) {
            return null;
        }

        if (app()->runningUnitTests()) {
            return SaleInvoice::query()->find($invoiceId);
        }

        static $cache = [];

        return $cache[$invoiceId] ??= SaleInvoice::query()->find($invoiceId);
    }

    /**
     * Retrieve all eligible items from a SaleInvoice formatted for the return repeater.
     *
     * Iterates through the original invoice items, checking for any remaining returnable quantities.
     * For each eligible item, it calculates the refund breakdown and constructs an array schema
     * that directly maps to the 'items' repeater form fields.
     *
     * @param  SaleInvoice  $invoice  The original sale invoice model.
     * @return array<string, array<string, mixed>> An associative array of return items keyed by 'item_{original_item_id}'.
     */
    protected static function getAllInvoiceItemsForReturn(SaleInvoice $invoice): array
    {
        $invoice->loadMissing(['items.variant.product', 'items.variant.barcodes']);

        $items = [];
        foreach ($invoice->items as $originalItem) {
            $maxReturnable = $originalItem->getRemainingReturnableQuantity();
            if ($maxReturnable > 0) {
                $barcodes = $originalItem->variant->barcodes->pluck('barcode')->toArray();
                $key = 'item_'.$originalItem->id;
                // todo review this calculateRefundBreakdown fun
                $refundBreakdown = SaleInvoiceService::make()->calculateRefundBreakdown($originalItem);

                $items[$key] = [
                    'original_item_id' => $originalItem->id,
                    'product_variant_id' => $originalItem->product_variant_id,
                    'barcodes' => $barcodes,
                    'product_name' => $originalItem->variant->full_qualified_name,
                    'max_returnable' => $maxReturnable,
                    'quantity' => $maxReturnable,
                    'unit_price' => (float) $originalItem->unit_price,
                    'unit_discount_amount' => (float) $originalItem->monetary_unit_discount_amount,
                    'unit_prorated_global_discount' => (float) $refundBreakdown['unit_prorated_global_discount'],
                    'effective_unit_refund' => (float) $refundBreakdown['effective_unit_refund'],
                    'item_refund_total' => round($maxReturnable * $refundBreakdown['effective_unit_refund'], 2),
                    'notes' => null,
                ];
            }
        }

        return $items;
    }

    /**
     * Calculate and update the total refund amounts dynamically based on form state.
     *
     * Sums up the 'item_refund_total' of all items in the repeater. Then evaluates any 'extraItems'
     * (additions or subtractions) and computes the final 'total_refund_amount'. Updates the form
     * state dynamically for real-time UI reflection.
     *
     * @param  Get  $get  The Filament form state getter.
     * @param  Set  $set  The Filament form state setter.
     * @param  string  $prefix  The state path prefix, useful when calculating totals from within a nested component (e.g., '../../').
     */
    protected static function calcTotalAmount(Get $get, Set $set, $livewire = null, string $prefix = ''): void
    {
        $items = $get($prefix.'items') ?? [];
        $itemsRefundTotal = collect($items)->sum('item_refund_total');

        $extraItems = $get($prefix.'extraItems') ?? [];
        $extraItemsTotal = collect($extraItems)->sum(function ($extra) {
            $amount = (float) ($extra['amount'] ?? 0.0);
            $actionType = $extra['action_type'] ?? null;

            if ($actionType === ExtraItemActionType::Addition->value) {
                return $amount;
            } elseif ($actionType === ExtraItemActionType::Subtraction->value) {
                return -$amount;
            } else {
                return 0.0;
            }

        });

        $totalRefundAmount = $itemsRefundTotal + $extraItemsTotal;

        $set($prefix.'items_refund_total', round($itemsRefundTotal, 2));
        $set($prefix.'extra_items_total', round($extraItemsTotal, 2));
        $set($prefix.'total_refund_amount', round($totalRefundAmount, 2));

        if ($livewire) {
            $livewire->resetValidation('data.total_refund_amount');
            $livewire->validateOnly('data.total_refund_amount');
        }
    }

    protected static function calculateLine(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $effective_unit_refund = (float) ($get('effective_unit_refund') ?? 0);
        $item_refund_total = $effective_unit_refund * $quantity;

        $set('item_refund_total', round($item_refund_total, 2));

    }
}
