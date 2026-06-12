<?php

namespace App\Filament\Resources\PurchaseReturns\Schemas;

use App\Enums\ExtraItemActionType;
use App\Models\InvoiceExtraItemPreset;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PurchaseReturnForm
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
                    Section::make(__('purchase_return.purchase_return'))
                        ->compact()
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->columnSpanFull()
                        ->columns(4)
                        ->schema([
                            Hidden::make('original_invoice_id')
                                ->default(static::getOriginalInvoiceIdFromRequest())
                                ->required(),

                            TextInput::make('invoice_number_input')
                                ->label(__('purchase_return.original_invoice_id'))
                                ->required()
                                ->default(function () {
                                    // TODO: Refactor this to use a centralized getCachedOriginalInvoice method like SaleReturnInvoiceForm
                                    $invoiceId = static::getOriginalInvoiceIdFromRequest();

                                    return $invoiceId ? PurchaseInvoice::query()->find($invoiceId)?->invoice_number : null;
                                })
                                ->live(onBlur: true)
                                ->stateBindingModifiers(['blur', 'trim'])
                                ->afterStateUpdated(function ($state, Get $get, Set $set, $livewire) {
                                    $set('items', []); // clear items
                                    self::clearOriginalInvoiceMetadata($set);
                                    static::calcTotalAmount($get, $set);

                                    if ($state) {
                                        $invoice = PurchaseInvoice::query()
                                            ->returnable()
                                            ->where('invoice_number', $state)
                                            ->first();

                                        if ($invoice) {
                                            self::hydrateOriginalInvoiceMetadata($set, $invoice);
                                            Notification::make()->success()->title(__('purchase_return.invoice_found_success'))->send();
                                            $livewire->dispatch('play-sound-success');
                                        } else {
                                            Notification::make()->warning()->title(__('purchase_return.invoice_not_found'))->send();
                                            $livewire->dispatch('play-sound-error');
                                        }
                                    }
                                })
                                ->formatStateUsing(fn ($state, $record) => $record ? $record->originalInvoice?->invoice_number : $state)
                                ->dehydrated(false)
                                ->helperText(__('purchase_return.invoice_search_helper'))
                                ->prefixAction(
                                    Action::make('search')
                                        ->icon('heroicon-m-magnifying-glass')
                                        ->label(__('purchase_return.search'))
                                )
                                ->columnSpan(1),

                            Select::make('vendor_id')
                                ->label(__('purchase_return.vendor'))
                                ->relationship('vendor', 'name')
                                ->default(function () {
                                    // TODO: Refactor this to use a centralized getCachedOriginalInvoice method like SaleReturnInvoiceForm
                                    $invoiceId = static::getOriginalInvoiceIdFromRequest();

                                    return $invoiceId ? PurchaseInvoice::query()->find($invoiceId)?->vendor_id : null;
                                })
                                ->required()
                                ->disabled()
                                ->dehydrated()
                                ->columnSpan(1),

                            static::getStoreIDInput($user),

                            DatePicker::make('returned_at')
                                ->label(__('purchase_return.returned_at'))
                                ->helperText(__('purchase_return.returned_at_helper'))
                                ->required()
                                ->displayFormat('d/m/Y')
                                ->default(now())
                                ->maxDate(now())
                                ->columnSpan(1),

                            Textarea::make('return_reason')
                                ->label(__('purchase_return.return_reason'))
                                ->helperText(__('purchase_return.return_reason_helper'))
                                ->required()
                                ->rows(1)
                                ->columnSpan(2),

                            Textarea::make('notes')
                                ->label(__('purchase_return.notes'))
                                ->helperText(__('purchase_return.notes_helper'))
                                ->rows(1)
                                ->columnSpan(2),
                        ]),

                    Section::make(__('purchase_return.items'))
                        ->compact()
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->columnSpanFull()
                        ->headerActions([
                            Action::make('add_all_items')
                                ->label(__('purchase_return.return_all_items'))
                                ->icon('heroicon-o-bars-arrow-down')
                                ->color('primary')
                                ->action(function (Get $get, Set $set) {
                                    $invoiceId = $get('original_invoice_id');
                                    if (! $invoiceId) {
                                        return;
                                    }

                                    $invoice = PurchaseInvoice::with(['items.variant.product', 'items.variant.barcodes'])->find($invoiceId);
                                    if (! $invoice) {
                                        return;
                                    }

                                    $items = static::getAllInvoiceItemsForReturn($invoice);
                                    $set('items', $items);
                                    static::calcTotalAmount($get, $set);
                                })
                                ->visible(
                                    fn (Get $get, $livewire) => filled($get('original_invoice_id')) &&
                                        ! ($livewire instanceof ViewRecord)
                                ),
                        ])
                        ->schema([
                            Select::make('product_search')
                                ->allowHtml()
                                ->label(__('purchase_return.search_by_product'))
                                ->placeholder(__('purchase_return.search_by_product_placeholder'))
                                ->helperText(__('purchase_return.search_by_product_helper'))
                                ->visible(
                                    fn (Get $get, $livewire) => filled($get('original_invoice_id')) &&
                                        ! ($livewire instanceof ViewRecord)
                                )
                                ->options(function (Get $get) {
                                    $invoiceId = $get('original_invoice_id');
                                    if (! $invoiceId) {
                                        return [];
                                    }

                                    $items = PurchaseInvoiceItem::with(['variant.product', 'variant.barcodes'])
                                        ->where('purchase_invoice_id', $invoiceId)
                                        ->get();

                                    return $items->mapWithKeys(function (PurchaseInvoiceItem $item) {
                                        $fullName = badge($item->variant->full_qualified_name);
                                        $barcodes = $item->variant->getAllBarcodesAsString();
                                        $barcodeText = $barcodes ? badge($barcodes) : '';
                                        $max = $item->getRemainingReturnableQuantity();
                                        $maxLabel = badge(__('purchase_return.max_suffix').$max);

                                        return [$item->id => "<div class='flex flex-wrap items-center gap-2' dir='auto'>$fullName $barcodeText $maxLabel</div>"];
                                    })->toArray();
                                })
                                ->searchable()
                                ->live()
                                ->preload()
                                ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) {
                                    if (! $state) {
                                        return;
                                    }
                                    $set('product_search', null);

                                    $originalItem = PurchaseInvoiceItem::with(['variant.product', 'variant.barcodes'])->find($state);
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

                                    $originalItem = PurchaseInvoiceItem::query()->find($data['original_item_id']);
                                    if ($originalItem) {
                                        $data['max_returnable'] = $originalItem->getRemainingReturnableQuantity($record->id);
                                    }

                                    return $data;
                                })
                                ->hiddenLabel()
                                ->compact()
                                ->deleteAction(
                                    fn (Action $action) => $action->after(function (Get $get, Set $set) {
                                        static::calcTotalAmount($get, $set);
                                    })
                                )
                                ->itemLabel(function (array $state): ?HtmlString {
                                    $barcodes = $state['barcodes'] ?? [];
                                    $productHtml = badge(e($state['product_name'] ?? __('app.unknown_product')));

                                    if (empty($barcodes)) {
                                        return new HtmlString("<div class='flex items-center'>$productHtml</div>");
                                    }

                                    $badgesHtml = collect($barcodes)->map(function ($barcode) {
                                        return badge(e($barcode));

                                    })->implode(' ');

                                    return new HtmlString("<div class='flex items-center'>$productHtml<span class='text-sm text-gray-500' style='margin-inline-end: 0.5rem;'>".__('purchase_return.barcode').":</span>{$badgesHtml}</div>");
                                })
                                ->schema([
                                    Hidden::make('original_item_id')->required(),
                                    Hidden::make('product_variant_id')->required(),
                                    Hidden::make('max_returnable')->dehydrated(false),

                                    TextInput::make('quantity')
                                        ->label(__('purchase_return.quantity'))
                                        ->numeric()
                                        ->required()
                                        ->helperText(__('purchase_return.quantity_tooltip'))
                                        ->rules(['min:0.001'])
                                        ->step(1)
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                            $max = (float) $get('max_returnable');
                                            if ((float) $state > $max) {
                                                $set('quantity', $max);
                                                Notification::make()->warning()->title(__('purchase_return.quantity_adjusted'))->send();
                                            }
                                            self::recalculateLine($get, $set);

                                            static::calcTotalAmount($get, $set, '../../');
                                        })
                                        ->suffix(fn (Get $get) => __('purchase_return.max_suffix').' '.$get('max_returnable'))
                                        ->columnSpan(1),

                                    // TODO: Add custom permission 'override_purchase_return_unit_cost' to Company permissions.
                                    //       Allow users with this permission to edit this field and handle side effects.
                                    TextInput::make('unit_cost')
                                        ->label(__('purchase_return.unit_cost'))
                                        ->numeric()
                                        ->required()
                                        ->helperText(__('purchase_return.unit_cost_tooltip'))
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(1),

                                    TextInput::make('subtotal')
                                        ->label(__('purchase_return.subtotal'))
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated()
                                        ->helperText(__('purchase_return.subtotal_tooltip'))
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->columnSpan(1),

                                    Textarea::make('notes')
                                        ->label(__('purchase_return.item_notes'))
                                        ->maxLength(255)
                                        ->rows(1)
                                        ->helperText(__('purchase_return.notes_tooltip'))
                                        ->columnSpan(1),

                                ])
                                ->columns(4)
                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false)
                                ->defaultItems(0),
                        ]),

                    Section::make(__('app.extra_items'))
                        ->compact()
                        ->icon('heroicon-o-plus-circle')
                        ->columnSpanFull()
                        ->schema([
                            Repeater::make('extraItems')
                                ->compact()
                                ->relationship('extraItems')
                                ->addActionLabel(__('app.add_more'))
                                ->hiddenLabel()
                                ->defaultItems(0)
                                ->itemLabel(fn (array $state) => $state['name'] ?? '')
                                ->schema([
                                    Select::make('invoice_extra_item_preset_id')
                                        ->label(__('app.preset'))
                                        ->helperText(__('purchase_invoice.preset_helper'))
                                        ->dehydrated(false)
                                        ->live()
                                        ->searchable()
                                        ->options((InvoiceExtraItemPreset::query()->forPurchaseReturn()->active()->pluck('name', 'id')))
                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                            $preset = InvoiceExtraItemPreset::find((int) $state);
                                            if ($preset) {
                                                $set('name', $preset->name);
                                                $set('action_type', $preset->action_type);
                                                $set('amount', $preset->amount);
                                            }
                                            static::calcTotalAmount($get, $set, '../../');
                                        }),
                                    TextInput::make('name')
                                        ->label(__('app.name'))
                                        ->helperText(__('purchase_invoice.extra_name_tooltip'))
                                        ->required(),
                                    Select::make('action_type')
                                        ->label(__('extra_item.action_type'))
                                        ->options(ExtraItemActionType::class)
                                        ->helperText(__('purchase_invoice.extra_type_tooltip'))
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            static::calcTotalAmount($get, $set, '../../');
                                        }),
                                    TextInput::make('amount')
                                        ->label(__('app.amount'))
                                        ->helperText(__('purchase_invoice.extra_amount_tooltip'))
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required()
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            static::calcTotalAmount($get, $set, '../../');
                                        }),
                                    Textarea::make('notes')
                                        ->label(__('app.notes'))
                                        ->helperText(__('purchase_invoice.extra_notes_tooltip'))
                                        ->rows(1)
                                        ->maxLength(255),
                                ])
                                ->deleteAction(
                                    fn (Action $action) => $action->after(fn (Get $get, Set $set) => static::calcTotalAmount($get, $set))
                                )
                                ->columns(5),
                        ]),

                    Section::make(__('app.summary'))
                        ->compact()
                        ->icon('heroicon-o-calculator')
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('subtotal')
                                ->label(__('app.items_refund_total'))
                                ->helperText(__('purchase_return.items_refund_helper'))
                                ->disabled()
                                ->dehydrated()
                                ->minValue(0)
                                ->prefix($user->company->currency_symbol ?? 'ج.م'),
                            TextInput::make('extra_items_total')
                                ->label(__('app.extra_items_total'))
                                ->helperText(__('purchase_invoice.extra_items_helper'))
                                ->disabled()
                                ->dehydrated()
                                ->prefix($user->company->currency_symbol ?? 'ج.م'),
                            TextInput::make('total_amount')
                                ->label(__('purchase_return.grand_total'))
                                ->helperText(__('purchase_return.total_refund_helper'))
                                ->disabled()
                                ->dehydrated()
                                ->minValue(0)
                                ->extraInputAttributes(['class' => 'text-xl font-bold'])
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->afterStateHydrated(function (Get $get, Set $set) {
                                    static::calcTotalAmount($get, $set);
                                })
                                ->columnSpan(1),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    /**
     * Recalculate line_total when qty or unit_cost changes.
     */
    private static function recalculateLine(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitCost = (float) ($get('unit_cost') ?? 0);
        $subtotal = round($quantity * $unitCost, 2);

        $set('subtotal', $subtotal);
    }

    public static function getAllInvoiceItemsForReturn(PurchaseInvoice $invoice): array
    {
        $invoice->loadMissing(['items.variant.product', 'items.variant.barcodes']);

        $items = [];
        foreach ($invoice->items as $originalItem) {
            $maxReturnable = $originalItem->getRemainingReturnableQuantity();
            if ($maxReturnable > 0) {
                $barcodes = $originalItem->variant->barcodes->pluck('barcode')->toArray();
                $key = 'item_'.$originalItem->id;

                $items[$key] = [
                    'original_item_id' => $originalItem->id,
                    'product_variant_id' => $originalItem->product_variant_id,
                    'barcodes' => $barcodes,
                    'product_name' => $originalItem->variant->full_qualified_name,
                    'max_returnable' => $maxReturnable,
                    'quantity' => $maxReturnable,
                    'unit_cost' => (float) $originalItem->unit_cost,
                    'subtotal' => round($maxReturnable * (float) $originalItem->unit_cost, 2),
                    'notes' => null,
                ];
            }
        }

        return $items;
    }

    protected static function addOriginalItemToReturn(PurchaseInvoiceItem $originalItem, Get $get, Set $set, $livewire): void
    {
        $maxReturnable = $originalItem->getRemainingReturnableQuantity();
        if ($maxReturnable <= 0) {
            Notification::make()
                ->warning()
                ->title(__('purchase_return.no_remaining_quantity'))
                ->body(__('purchase_return.no_remaining_quantity_body'))
                ->send();
            $livewire->dispatch('play-sound-error');
            $livewire->dispatch('focus-barcode');

            return;
        }

        $items = $get('items') ?? [];
        $key = 'item_'.$originalItem->id;

        // Check for duplicate items in existing lines
        $alreadyExists = collect($items)->contains(
            fn ($item) => ((int) ($item['original_item_id'] ?? 0)) === (int) $originalItem->id
        );

        if ($alreadyExists) {
            Notification::make()->warning()->title(__('purchase_return.item_already_added'))->send();
            $livewire->dispatch('play-sound-error');
            $livewire->dispatch('focus-barcode');

            return;
        }

        $barcodes = $originalItem->variant->barcodes->pluck('barcode')->toArray();

        $items[$key] = [
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $originalItem->product_variant_id,
            'barcodes' => $barcodes,
            'product_name' => $originalItem->variant->full_qualified_name,
            'quantity' => 1,
            'unit_cost' => (float) $originalItem->unit_cost,
            'subtotal' => round((float) $originalItem->unit_cost, 2),
            'max_returnable' => $maxReturnable,
            'notes' => null,
        ];

        $set('items', $items);
        static::calcTotalAmount($get, $set);
        $livewire->dispatch('play-sound-success');
        $livewire->dispatch('focus-barcode');
    }

    private static function calcTotalAmount(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix.'items') ?? [];
        $itemsRefundTotal = collect($items)->sum('subtotal');

        $extraItemsTotal = self::calculateExtraItemsTotal($get, $prefix);

        $totalRefundAmount = $itemsRefundTotal + $extraItemsTotal;

        if ($totalRefundAmount < 0) {
            Notification::make()
                ->warning()
                ->title(__('purchase_return.negative_total_warning'))
                ->body(__('purchase_return.deductions_exceed_total_message'))
                ->send();
        }

        $set($prefix.'subtotal', round($itemsRefundTotal, 2));
        $set($prefix.'extra_items_total', round($extraItemsTotal, 2));
        $set($prefix.'total_amount', round($totalRefundAmount, 2));
    }

    public static function calculateExtraItemsTotal(Get $get, string $prefix = ''): float
    {
        $extraItems = $get($prefix.'extraItems') ?? [];
        $total = 0.0;

        foreach ($extraItems as $extraItem) {
            $amount = (float) ($extraItem['amount'] ?? 0);
            $actionType = ExtraItemActionType::toString($extraItem['action_type'] ?? null);

            if ($actionType === ExtraItemActionType::Addition->value) {
                $total += $amount;
            } elseif ($actionType === ExtraItemActionType::Subtraction->value) {
                $total -= $amount;
            }
        }

        return $total;
    }

    /**
     * Clear the original invoice metadata from the form state.
     *
     * This is typically called when the user clears the invoice search input or enters an invalid invoice number,
     * ensuring that no stale data remains.
     *
     * @param  Set  $set  The Filament form state setter.
     */
    private static function clearOriginalInvoiceMetadata(Set $set): void
    {
        self::hydrateOriginalInvoiceMetadata($set, null);
    }

    /**
     * Hydrate the form state with metadata from the selected original purchase invoice.
     *
     * Extracts relevant fields (e.g., vendor_id, store_id, original_invoice_id) from the original
     * PurchaseInvoice model and populates the corresponding form fields.
     *
     * @param  Set  $set  The Filament form state setter.
     * @param  PurchaseInvoice|null  $invoice  The original purchase invoice model, or null to clear metadata.
     */
    protected static function hydrateOriginalInvoiceMetadata(Set $set, ?PurchaseInvoice $invoice): void
    {
        $set('original_invoice_id', $invoice?->id);
        $set('vendor_id', $invoice?->vendor_id);
        $set('store_id', $invoice?->store_id);
    }

    private static function getStoreIDInput(User $user)
    {
        if ($user->isStoreLevel()) {
            return Hidden::make('store_id')
                ->default(fn () => $user->store_id)
                ->disabled()
                ->dehydrated(fn (string $operation): bool => $operation !== 'edit');
        }

        return Select::make('store_id')
            ->label(__('purchase_return.store'))
            ->relationship('store', lang_suffix('name'))
            ->searchable(['name_en', 'name_ar'])
            ->preload()
            ->default(function () {
                // TODO: Refactor this to use a centralized getCachedOriginalInvoice method like SaleReturnInvoiceForm
                $invoiceId = static::getOriginalInvoiceIdFromRequest();

                return $invoiceId ? PurchaseInvoice::query()->find($invoiceId)?->store_id : null;
            })
            ->required()
            ->disabled()
            ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
            ->helperText(__('purchase_return.store_helper'))
            ->columnSpan(1);
    }

    /**
     * Retrieve and validate the 'original_invoice_id' from the current HTTP request query parameters.
     *
     * Used to pre-fill the return form if the user navigates from a specific invoice to create a return.
     *
     * @return int|null The validated original invoice ID, or null if missing/invalid.
     */
    protected static function getOriginalInvoiceIdFromRequest(): ?int
    {
        return request()->integer('original_invoice_id') ?: null;
    }
}
