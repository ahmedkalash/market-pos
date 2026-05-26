<?php

namespace App\Filament\Resources\SaleInvoices\Schemas;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\ShippingDestination;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SaleInvoiceForm
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
                ])->columnSpanFull()
                ->schema([
                    Section::make(__('sale_invoice.sale_invoice'))
                        ->icon('heroicon-o-document-arrow-up')
                        ->schema([
                            TextEntry::make('draft_warning')
                                ->hiddenLabel()
                                ->state(new HtmlString('<div class="text-warning-600 bg-warning-50 p-3 rounded-lg dark:text-warning-400 dark:bg-warning-400/10 text-sm font-medium">'.__('sale_invoice.draft_prices_warning').'</div>'))
                                ->hidden(fn (?Model $record, string $operation) => $operation === 'create' || $record?->isFinalized())
                                ->columnSpanFull(),

                            Select::make('store_id')
                                ->label(__('sale_invoice.store'))
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

                            Select::make('customer_id')
                                ->label(__('customer.model_label'))
                                ->relationship('customer', 'name')
                                ->searchable()
                                ->createOptionForm([
                                    Grid::make(2)->schema([
                                        TextInput::make('name')
                                            ->label(__('customer.name'))
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->label(__('customer.phone'))
                                            ->tel()
                                            ->maxLength(255),
                                    ]),
                                ]),
                            Select::make('payment_method')
                                ->label(__('sale_invoice.payment_method'))
                                ->options([
                                    PaymentMethod::Cash->value => __('sale_invoice.payment_method_cash'),
                                    PaymentMethod::Card->value => __('sale_invoice.payment_method_card'),
                                    PaymentMethod::Split->value => __('sale_invoice.payment_method_split'),
                                ])
                                ->required(),
                        ])->columns(2),

                    Section::make(__('sale_invoice.notes'))
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->schema([
                            Textarea::make('notes')
                                ->label(__('sale_invoice.notes'))
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),

                    Section::make(__('sale_invoice.items'))
                        ->icon('heroicon-o-shopping-cart')
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('barcode_scanner')
                                ->label(__('sale_invoice.barcode_scanner'))
                                ->placeholder(__('sale_invoice.scan_barcode'))
                                ->helperText(__('sale_invoice.barcode_scanner_helper'))
                                ->hiddenOn('view')
                                ->autofocus()
                                ->extraInputAttributes([
                                    'x-on:focus-barcode.window' => 'setTimeout(() => $el.focus(), 10)',
                                ])
                                ->live(onBlur: true)
                                ->prefixAction(
                                    Action::make('search')
                                        ->icon('heroicon-m-magnifying-glass')
                                        ->label(__('sale_invoice.search'))
                                )
                                ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) {
                                    if (! $state) {
                                        return;
                                    }
                                    $set('barcode_scanner', null);

                                    $barcodeRecord = ProductBarcode::where('barcode', $state)->first();
                                    if (! $barcodeRecord) {
                                        Notification::make()->warning()->title(__('sale_invoice.product_not_found'))->send();
                                        $livewire->dispatch('play-sound-error');
                                        $livewire->dispatch('focus-barcode');

                                        return;
                                    }

                                    // Check store boundary
                                    $storeId = $get('store_id');
                                    $variant = ProductVariant::with(['product.taxClass', 'barcodes'])
                                        ->find($barcodeRecord->product_variant_id);

                                    if (! $variant) {
                                        Notification::make()->warning()->title(__('sale_invoice.product_not_found'))->send();
                                        $livewire->dispatch('play-sound-error');
                                        $livewire->dispatch('focus-barcode');

                                        return;
                                    }

                                    // Validate variant belongs to the selected store
                                    if ($storeId && $variant->product->store_id !== (int) $storeId) {
                                        Notification::make()->warning()->title(__('sale_invoice.product_wrong_store'))->send();
                                        $livewire->dispatch('play-sound-error');
                                        $livewire->dispatch('focus-barcode');

                                        return;
                                    }

                                    $items = $get('items') ?? [];
                                    $newKey = (string) Str::uuid();

                                    // Check for duplicate variant in existing items
                                    $alreadyExists = collect($items)->contains(
                                        fn ($item) => ((int) ($item['product_variant_id'] ?? 0)) === (int) $variant->id
                                    );

                                    if ($alreadyExists) {
                                        Notification::make()->warning()->title(__('sale_invoice.duplicate_barcode'))->send();
                                        $livewire->dispatch('play-sound-error');
                                        $livewire->dispatch('focus-barcode');

                                        return;
                                    }

                                    $locale = app()->getLocale();
                                    $productName = $variant->product->{"name_$locale"} ?? '';
                                    $variantName = $variant->{"name_$locale"} ?? '';
                                    $fullName = $variantName ? "{$productName} - {$variantName}" : $productName;

                                    $barcodes = $variant->barcodes->pluck('barcode')->toArray();

                                    $unitPrice = (float) $variant->retail_price;

                                    $items[$newKey] = [
                                        'product_variant_id' => $variant->id,
                                        'price_type' => PriceType::Retail->value,
                                        'barcodes' => $barcodes,
                                        'product_name' => $fullName,
                                        'quantity' => 1,
                                        'unit_price' => $unitPrice,
                                        'subtotal' => $unitPrice,
                                        'discount_type' => null,
                                        'unit_discount_amount' => null,
                                        'line_total_discount' => 0.0,
                                        'line_total' => round(1 * $unitPrice, 2),
                                        'notes' => null,
                                    ];

                                    $set('items', $items);
                                    self::recalculateTotals($get, $set);

                                    Notification::make()
                                        ->title(__('sale_invoice.item_added'))
                                        ->body($fullName)
                                        ->success()
                                        ->send();

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

                                    return new HtmlString("<div class='flex items-center'>".__('sale_invoice.barcode').': '.$badges.'</div>');
                                })
                                ->hiddenLabel()
                                ->compact()
                                ->schema([
                                    Hidden::make('product_variant_id')
                                        ->required(),

                                    Hidden::make('subtotal')
                                        ->default(0),

                                    TextInput::make('product_name')
                                        ->label(__('sale_invoice.product_name'))
                                        ->dehydrated(false)
                                        ->disabled()
                                        ->readOnly()
                                        ->columnSpan(3),

                                    Select::make('price_type')
                                        ->label(__('sale_invoice.price_type'))
                                        ->options(PriceType::class)
                                        ->default(PriceType::Retail)
                                        ->hintIcon('heroicon-m-information-circle', tooltip: __('sale_invoice.price_type_helper_text'))
                                        ->required()
                                        ->disableOptionWhen(function (string $value, Get $get) {
                                            if ($value === PriceType::Wholesale->value) {
                                                $variantId = $get('product_variant_id');

                                                return ! self::getCachedVariant($variantId)?->wholesale_enabled;
                                            }

                                            return false;
                                        })
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set, $state, $livewire) {
                                            if (! $variant = self::getCachedVariant($get('product_variant_id'))) {
                                                return;
                                            }

                                            $priceType = PriceType::toString($state);

                                            if ($priceType == PriceType::Wholesale->value) {
                                                $set('unit_price', (float) $variant->wholesale_price);
                                            } else {
                                                $set('unit_price', (float) $variant->retail_price);
                                            }
                                            self::recalculateLine($get, $set);
                                            self::recalculateTotals($get, $set, '../../');
                                        })
                                        ->columnSpan(2),

                                    TextInput::make('quantity')
                                        ->label(__('sale_invoice.quantity'))
                                        ->numeric()
                                        ->required()
                                        ->default(1)
                                        ->minValue(0.001)
                                        ->step(0.001)
                                        ->hintIcon(
                                            'heroicon-m-information-circle',
                                            tooltip: function (Get $get) {
                                                $variant = self::getCachedVariant($get('product_variant_id'));
                                                if ($variant && $variant->hasWholesaleQtyThreshold()) {
                                                    return __('sale_invoice.wholesale_qty_tooltip', ['qty' => (float) $variant->wholesale_qty_threshold]);
                                                }

                                                return __('sale_invoice.quantity_tooltip');
                                            }
                                        )
                                        ->suffix(function (Get $get) {
                                            $priceType = $get('price_type');
                                            $priceTypeValue = PriceType::toString($priceType);

                                            if ($priceTypeValue === PriceType::Wholesale->value) {
                                                $variant = self::getCachedVariant($get('product_variant_id'));
                                                if ($variant && $variant->hasWholesaleQtyThreshold()) {
                                                    return __('app.min').': '.(float) $variant->wholesale_qty_threshold;
                                                }
                                            }

                                            return null;
                                        })
                                        ->helperText(function (Get $get) {
                                            $priceType = $get('price_type');
                                            $priceTypeValue = PriceType::toString($priceType);

                                            if ($priceTypeValue === PriceType::Wholesale->value) {
                                                $variant = self::getCachedVariant($get('product_variant_id'));
                                                if ($variant && $variant->hasWholesaleQtyThreshold()) {
                                                    return new HtmlString('<span class="text-danger-600 dark:text-danger-400 font-medium">'.__('sale_invoice.wholesale_qty_tooltip', ['qty' => (float) $variant->wholesale_qty_threshold]).'</span>');
                                                }
                                            }

                                            return null;
                                        })
                                        ->disabled(fn (Get $get) => ! $get('product_variant_id'))
                                        ->rules([
                                            function (Get $get) {
                                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    $priceType = $get('price_type');
                                                    $priceTypeValue = PriceType::toString($priceType);

                                                    if ($priceTypeValue === PriceType::Wholesale->value) {
                                                        $variant = self::getCachedVariant($get('product_variant_id'));
                                                        if ($variant && $variant->hasWholesaleQtyThreshold() && $value < $variant->wholesale_qty_threshold) {
                                                            $fail(__('sale_invoice.wholesale_qty_tooltip', ['qty' => (float) $variant->wholesale_qty_threshold]));
                                                        }
                                                    }
                                                };
                                            },
                                        ])
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::recalculateLine($get, $set);
                                            self::recalculateTotals($get, $set, '../../');
                                        })
                                        ->columnSpan(3),

                                    TextInput::make('unit_price')
                                        ->label(__('sale_invoice.unit_price'))
                                        ->numeric()
                                        ->required()
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->minValue(function (Get $get) {
                                            $variant = self::getCachedVariant($get('product_variant_id'));
                                            if (! $variant) {
                                                return 0;
                                            }

                                            $priceTypeEnum = PriceType::try($get('price_type') ?? null);
                                            if (! $priceTypeEnum) {
                                                return 0;
                                            }

                                            return $variant->getMinimumAllowedPrice($priceTypeEnum);
                                        })
                                        ->step(0.01)
                                        ->helperText(function (Get $get) use ($user) {
                                            $variant = self::getCachedVariant($get('product_variant_id'));
                                            if (! $variant) {
                                                return null;
                                            }

                                            $priceTypeEnum = PriceType::try($get('price_type') ?? null);
                                            if (! $priceTypeEnum) {
                                                return null;
                                            }

                                            if (! $variant->isPriceNegotiable($priceTypeEnum)) {
                                                return __('sale_invoice.non_negotiable');
                                            }

                                            $minPrice = $variant->getMinimumAllowedPrice($priceTypeEnum);

                                            $decimalPrecision = (int) ($user->company->decimal_precision ?? 2);
                                            $decimalSeparator = $user->company->decimal_separator ?? '.';
                                            $thousandSeparator = $user->company->thousand_separator ?? ',';
                                            $formattedPrice = number_format($minPrice, $decimalPrecision, $decimalSeparator, $thousandSeparator);

                                            return __('app.min').': '.$formattedPrice;
                                        })
                                        ->hintIcon('heroicon-m-information-circle', tooltip: __('sale_invoice.unit_price_tooltip'))
                                        ->disabled(fn (Get $get) => ! $get('product_variant_id'))
                                        ->readOnly()
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::recalculateLine($get, $set);
                                            self::recalculateTotals($get, $set, '../../');
                                        })
                                        ->columnSpan(2),

                                    TextInput::make('subtotal')
                                        ->label(__('sale_invoice.subtotal'))
                                        ->numeric()
                                        ->required()
                                        ->readOnly()
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->helperText(__('sale_invoice.subtotal_helper_text'))
                                        ->columnSpan(3),

                                    Select::make('discount_type')
                                        ->label(__('sale_invoice.discount_type'))
                                        ->options(DiscountType::class)
                                        ->helperText(fn (Get $get) => self::isDiscountDisabled($get) ? __('sale_invoice.discount_disabled_helper_text') : __('sale_invoice.discount_type_helper_text'))
                                        ->disabled(fn (Get $get) => self::isDiscountDisabled($get))
                                        ->dehydrated()
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set, $livewire, Select $component) {
                                            self::recalculateLine($get, $set);
                                            self::recalculateTotals($get, $set, '../../');

                                            $unitDiscountAmountPath = str_replace($component->getName(), 'unit_discount_amount', $component->getStatePath());
                                            $livewire->validateOnly($unitDiscountAmountPath);
                                        })
                                        ->columnSpan(3),

                                    TextInput::make('unit_discount_amount')
                                        ->label(__('sale_invoice.unit_discount_amount'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->required(fn (Get $get) => filled($get('discount_type')))
                                        ->helperText(fn (Get $get) => self::isDiscountDisabled($get) ? __('sale_invoice.discount_disabled_helper_text') : __('sale_invoice.unit_discount_helper_text'))
                                        ->disabled(fn (Get $get) => self::isDiscountDisabled($get) || blank($get('discount_type')))
                                        ->dehydrated()
                                        ->prefix(function (TextInput $component) use ($user) {
                                            $discountTypeStatePath = str_replace($component->getName(), 'discount_type', $component->getStatePath());
                                            $currency = addslashes($user->company->currency_symbol ?? 'ج.م');
                                            $percentage = DiscountType::Percentage->value;

                                            return new HtmlString(
                                                '<span x-data="{}" x-text="$wire.get(\''.$discountTypeStatePath.'\') === \''.$percentage.'\' ? \'%\' : \''.$currency.'\'"></span>'
                                            );
                                        })
                                        ->suffix(function (Get $get) {
                                            $discountType = DiscountType::toString($get('discount_type'));
                                            if (! $discountType) {
                                                return null;
                                            }

                                            $variant = self::getCachedVariant($get('product_variant_id'));
                                            if (! $variant) {
                                                return null;
                                            }

                                            // Check if the item allows discounting based on price type
                                            $priceTypeEnum = PriceType::try($get('price_type') ?? null);
                                            if (! $priceTypeEnum) {
                                                return null;
                                            }

                                            if (! $variant->isPriceNegotiable($priceTypeEnum)) {
                                                return __('sale_invoice.max_allowed_discount', ['max' => 0]);
                                            }

                                            // Determine the absolute floor limit for the item's unit price
                                            $minPrice = $variant->getMinimumAllowedPrice($priceTypeEnum);
                                            $unitPrice = (float) ($get('unit_price') ?? 0);

                                            // The max fixed discount is the difference between current unit price and the min allowed price
                                            $maxDiscountAmount = max(0, $unitPrice - $minPrice);

                                            // If UI is in percentage mode, convert the max fixed discount into a percentage cap
                                            if ($discountType === DiscountType::Percentage->value) {
                                                $maxPercentage = $unitPrice > 0 ? ($maxDiscountAmount / $unitPrice) * 100 : 0;

                                                return __('sale_invoice.max_allowed_discount', ['max' => round($maxPercentage, 2).'%']);
                                            }

                                            return __('sale_invoice.max_allowed_discount', ['max' => round($maxDiscountAmount, 2)]);
                                        })
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function (Get $get, Set $set, $livewire, TextInput $component) {
                                            self::recalculateLine($get, $set);
                                            self::recalculateTotals($get, $set, '../../');
                                            $livewire->validateOnly($component->getStatePath());
                                        })
                                        ->rules([
                                            function (Get $get) {
                                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    if (! ($discountType = DiscountType::toString($get('discount_type'))) ||
                                                        empty($value = (float) $value) ||
                                                        ! ($variant = self::getCachedVariant($get('product_variant_id'))) ||
                                                        ! ($priceTypeEnum = PriceType::try($get('price_type') ?? null))
                                                    ) {
                                                        return;
                                                    }

                                                    // 1. Reject any discount if the item is flagged as non-negotiable
                                                    if (! $variant->isPriceNegotiable($priceTypeEnum)) {
                                                        $fail(__('sale_invoice.item_not_negotiable', ['item' => $variant->name() ?? '']));

                                                        return;
                                                    }

                                                    $minPrice = $variant->getMinimumAllowedPrice($priceTypeEnum);

                                                    // 2. Reject percentages over 100%
                                                    if ($discountType === DiscountType::Percentage->value && $value > 100) {
                                                        $fail(__('sale_invoice.percentage_exceeds_100'));

                                                        return;
                                                    }

                                                    $unitPrice = (float) ($get('unit_price') ?? 0);

                                                    // 3. Reject fixed discounts that exceed the base unit price (prevents negative price)
                                                    if ($discountType === DiscountType::Fixed->value && round($value, 2) > round($unitPrice, 2)) {
                                                        $fail(__('sale_invoice.discount_exceeds_unit_price', ['max' => $unitPrice]));

                                                        return;
                                                    }

                                                    // Calculate what the unit price will be after this proposed discount is applied
                                                    $unitPriceAfterItemDiscount = ($discountType == DiscountType::Fixed->value
                                                        ? $unitPrice - $value
                                                        : $unitPrice - ($unitPrice * ($value / 100)));

                                                    // 4. Reject if the post-discount price falls below the item's minimum threshold
                                                    if (round($unitPriceAfterItemDiscount, 2) < round($minPrice, 2)) {
                                                        $fail(__('sale_invoice.item_below_minimum', ['item' => $variant->name() ?? '', 'min' => $minPrice]));
                                                    }
                                                };
                                            },
                                        ])
                                        ->columnSpan(4),

                                    TextInput::make('line_total_discount')
                                        ->label(__('sale_invoice.line_total_discount'))
                                        ->numeric()
                                        ->readOnly()
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->helperText(fn (Get $get) => self::isDiscountDisabled($get) ? __('sale_invoice.discount_disabled_helper_text') : __('sale_invoice.line_total_discount_helper_text'))
                                        ->columnSpan(3)
                                        ->disabled(fn (Get $get) => self::isDiscountDisabled($get))
                                        ->dehydrated(),

                                    TextInput::make('line_total')
                                        ->label(__('sale_invoice.final_line_total'))
                                        ->numeric()
                                        ->readOnly()
                                        ->minValue(0)
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->helperText(__('sale_invoice.final_line_total_helper_text'))
                                        ->columnSpan(3),

                                    Textarea::make('notes')
                                        ->label(__('sale_invoice.item_notes'))
                                        ->maxLength(255)
                                        ->hintIcon('heroicon-m-information-circle', tooltip: __('sale_invoice.notes_tooltip'))
                                        ->columnSpan(13),
                                ])
                                ->columns(13)
                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false)
                                ->defaultItems(0)
                                ->deleteAction(
                                    fn ($action) => $action->after(fn (Get $get, Set $set) => self::recalculateTotals($get, $set))
                                ),

                            Section::make(__('sale_invoice.invoice_discount'))
                                ->icon('heroicon-o-receipt-percent')
                                ->schema([
                                    Select::make('discount_type')
                                        ->label(__('sale_invoice.discount_type'))
                                        ->options(DiscountType::class)
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set, $livewire, Select $component) {
                                            static::recalculateTotals($get, $set);

                                            $discountAmountPath = str_replace($component->getName(), 'discount_amount', $component->getStatePath());
                                            $livewire->validateOnly($discountAmountPath);
                                        }),
                                    TextInput::make('discount_amount')
                                        ->label(__('sale_invoice.discount_amount'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->required(fn (Get $get) => filled($get('discount_type')))
                                        ->disabled(fn (Get $get) => blank($get('discount_type')))
                                        ->dehydrated()
                                        ->prefix(function (TextInput $component) use ($user) {
                                            $discountTypeStatePath = str_replace($component->getName(), 'discount_type', $component->getStatePath());
                                            $currency = addslashes($user->company->currency_symbol ?? 'ج.م');
                                            $percentage = DiscountType::Percentage->value;

                                            return new HtmlString(
                                                '<span x-data="{}" x-text="$wire.get(\''.$discountTypeStatePath.'\') === \''.$percentage.'\' ? \'%\' : \''.$currency.'\'"></span>'
                                            );
                                        })
                                        ->suffix(function (Get $get) {
                                            if (! ($discountType = DiscountType::try($get('discount_type'))) ||
                                                empty($items = $get('items') ?? [])
                                            ) {
                                                return null;
                                            }

                                            $initialSubtotalsSum = collect($items)->sum(function ($item) {
                                                return (float) ($item['line_total'] ?? 0);
                                            });

                                            $sumOfMinimumAllowedPrices = self::getMinimumAllowedBasketTotal($items);

                                            if ($initialSubtotalsSum <= 0) {
                                                return null;
                                            }

                                            // The maximum allowed global discount is the total initial subtotal minus the total allowed minimums
                                            $maxFixedGlobalDiscount = max(0, $initialSubtotalsSum - $sumOfMinimumAllowedPrices);

                                            // If UI is in percentage mode, convert the max fixed global discount into a percentage
                                            if ($discountType === DiscountType::Percentage) {
                                                $maxPercentage = ($maxFixedGlobalDiscount / $initialSubtotalsSum) * 100;

                                                return __('sale_invoice.max_allowed_discount', ['max' => round($maxPercentage, 2).'%']);
                                            }

                                            return __('sale_invoice.max_allowed_discount', ['max' => round($maxFixedGlobalDiscount, 2)]);
                                        })
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function (Get $get, Set $set, $livewire, TextInput $component) {
                                            static::recalculateTotals($get, $set);
                                            $livewire->validateOnly($component->getStatePath());
                                        })
                                        ->rules([
                                            function (Get $get) {
                                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    $discountType = DiscountType::try($get('discount_type') ?? null);
                                                    $items = $get('items') ?? [];
                                                    if (! $discountType || empty($value) || empty($items)) {
                                                        return;
                                                    }

                                                    // 1. Reject percentages over 100%
                                                    if ($discountType === DiscountType::Percentage && (float) $value > 100) {
                                                        $fail(__('sale_invoice.percentage_exceeds_100'));

                                                        return;
                                                    }

                                                    $initialLinesTotalSum = collect($items)->sum(function ($item) {
                                                        return (float) ($item['line_total'] ?? 0);
                                                    });

                                                    // 2. Reject fixed discounts that exceed the total invoice amount
                                                    if ($discountType === DiscountType::Fixed &&
                                                        round((float) $value, 2) > round($initialLinesTotalSum, 2)) {
                                                        $fail(__('sale_invoice.grand_total_discount_exceeds_total'));

                                                        return;
                                                    }

                                                    // Determine the global discount monetary amount
                                                    $globalDiscountAmount = ($discountType === DiscountType::Fixed
                                                        ? (float) $value
                                                        : $initialLinesTotalSum * ((float) $value / 100));

                                                    $grandTotal = round($initialLinesTotalSum - $globalDiscountAmount, 2);

                                                    // 3. Basket-level minimum threshold validation
                                                    $sumOfMinimumAllowedPrices = self::getMinimumAllowedBasketTotal($items);

                                                    // Reject if the global discount pushes the grand total below the sum of all absolute minimums

                                                    if ($grandTotal < round($sumOfMinimumAllowedPrices, 2)) {
                                                        $fail(__('sale_invoice.invoice_below_minimum_after_global', [
                                                            'min' => number_format($sumOfMinimumAllowedPrices, 2),
                                                        ]));
                                                    }
                                                };
                                            },
                                        ]),
                                    TextInput::make('global_discount_amount')
                                        ->label(__('sale_invoice.global_discount_amount'))
                                        ->readOnly()
                                        ->dehydrated()
                                        ->numeric()
                                        ->prefix($user->company->currency_symbol ?? 'ج.م'),
                                ])
                                ->columns(3)
                                ->compact(),

                            Section::make(__('shipping.shipping_and_delivery'))
                                ->icon('heroicon-o-truck')
                                ->schema([
                                    Select::make('shipping_destination_id')
                                        ->label(__('shipping.destination'))
                                        ->relationship('shippingDestination', 'name', fn (Builder $query) => $query->active())
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            if ($state) {
                                                $destination = ShippingDestination::query()->find($state);
                                                if ($destination) {
                                                    $set('shipping_cost', (float) $destination->cost);
                                                    static::recalculateTotals($get, $set);
                                                }
                                            } else {
                                                $set('shipping_cost', 0);
                                                static::recalculateTotals($get, $set);
                                            }
                                        }),

                                    TextInput::make('shipping_cost')
                                        ->label(__('shipping.shipping_cost'))
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            static::recalculateTotals($get, $set);
                                        }),

                                    Textarea::make('shipping_address')
                                        ->label(__('shipping.shipping_address'))
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->compact(),

                            Section::make()
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('subtotal')
                                            ->label(__('sale_invoice.subtotal_amount'))
                                            ->readOnly()
                                            ->dehydrated()
                                            ->numeric()
                                            ->extraInputAttributes(['class' => 'text-lg font-semibold'])
                                            ->helperText(__('sale_invoice.subtotal_amount_helper'))
                                            ->prefix($user->company->currency_symbol ?? 'ج.م'),

                                        TextInput::make('grand_total_discount')
                                            ->label(__('sale_invoice.grand_total_discount'))
                                            ->readOnly()
                                            ->dehydrated()
                                            ->numeric()
                                            ->extraInputAttributes(['class' => 'text-lg font-semibold text-danger-600 dark:text-danger-400'])
                                            ->helperText(__('sale_invoice.grand_total_discount_helper'))
                                            ->prefix($user->company->currency_symbol ?? 'ج.م'),

                                        TextInput::make('total_amount')
                                            ->label(__('sale_invoice.total_amount'))
                                            ->readOnly()
                                            ->dehydrated()
                                            ->numeric()
                                            ->extraInputAttributes(['class' => 'text-xl font-bold text-success-600 dark:text-success-400'])
                                            ->helperText(__('sale_invoice.total_amount_helper'))
                                            ->prefix($user->company->currency_symbol ?? 'ج.م')
                                            ->afterStateHydrated(function (Get $get, Set $set) {
                                                static::recalculateTotals($get, $set);
                                            }),
                                    ]),
                                ]),
                        ]),
                ]),
        ]);
    }

    private static function isDiscountDisabled(Get $get): bool
    {
        $priceTypeEnum = PriceType::try($get('price_type') ?? null);
        $variant = self::getCachedVariant($get('product_variant_id'));

        if (! $variant || ! $priceTypeEnum) {
            return false;
        }

        return ! $variant->isPriceNegotiable($priceTypeEnum);
    }

    /**
     * Recalculates the financial totals for a single invoice item (line level).
     *
     * This method handles:
     * 1. Checking if the item allows price negotiation (discounting).
     * 2. Resetting discount fields if negotiation is not allowed.
     * 3. Calculating the fixed value of the discount (whether it was entered as fixed or percentage).
     * 4. Clamping the discount so it doesn't exceed the item's raw subtotal.
     * 5. Updating the final line total state variables.
     *
     * @param  Get  $get  Accessor for the current Repeater row state.
     * @param  Set  $set  Mutator for the current Repeater row state.
     */
    private static function recalculateLine(Get $get, Set $set): void
    {
        $priceTypeEnum = PriceType::try($get('price_type') ?? null);
        $variant = self::getCachedVariant($get('product_variant_id'));

        $isNegotiable = true;
        if ($variant && $priceTypeEnum) {
            // Determine negotiability based on the selected price type (wholesale vs retail)
            $isNegotiable = $variant->isPriceNegotiable($priceTypeEnum);
        }

        $discountType = DiscountType::toString($get('discount_type'));

        // If the item is strictly non-negotiable or the discount type is cleared, wipe out any applied discounts immediately.
        if (! $isNegotiable || blank($discountType)) {
            $set('discount_type', null);
            $set('unit_discount_amount', null);
            $set('line_total_discount', null);
        }

        // Retrieve raw input values
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $unitDiscountAmount = (float) ($get('unit_discount_amount') ?? 0);

        // Calculate the absolute monetary value of the discount for the entire line
        $lineTotalDiscount = 0.0;
        if ($discountType == DiscountType::Fixed->value) {
            // Fixed discount is applied per unit, so multiply by quantity
            $lineTotalDiscount = $unitDiscountAmount * $quantity;
        } elseif ($discountType == DiscountType::Percentage->value) {
            // Percentage discount is a fraction of the total raw subtotal
            $lineTotalDiscount = ($unitPrice * $quantity) * ($unitDiscountAmount / 100);
        }

        // Raw subtotal before any discounts
        $subtotalBeforeDiscount = $unitPrice * $quantity;

        // Prevent negative line totals by capping the discount at the subtotal amount
        $lineTotalDiscount = min($lineTotalDiscount, $subtotalBeforeDiscount);

        // Final line total after the item-level discount is subtracted
        $subtotalAfterDiscount = $subtotalBeforeDiscount - $lineTotalDiscount;

        // Update the Livewire component state with rounded final values
        $set('subtotal', round($subtotalBeforeDiscount, 2));
        $set('line_total_discount', round($lineTotalDiscount, 2));
        $set('line_total', round($subtotalAfterDiscount, 2));
    }

    /**
     * Recalculates the overarching grand totals for the entire invoice.
     *
     * This method handles:
     * 1. Summing up the post-discount subtotals of all individual items.
     * 2. Calculating the global (invoice-level) discount monetary value.
     * 3. Combining item-level discounts with the global discount for a grand total discount.
     * 4. Deducting the global discount to reach the final payable amount.
     *
     * @param  Get  $get  Accessor for the form state.
     * @param  Set  $set  Mutator for the form state.
     * @param  string  $prefix  Path prefix to resolve nested repeater state (e.g., '../../').
     */
    private static function recalculateTotals(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix.'items') ?? [];

        // Sum the 'line_total' of all items (these are subtotals AFTER item-level discounts)
        $initialLinesTotalSum = collect($items)->sum(function ($item) {
            return (float) ($item['line_total'] ?? 0);
        });

        // Sum the 'subtotal' of all items (these are subtotals BEFORE any discounts)
        $initialSubtotalsSum = collect($items)->sum(function ($item) {
            return (float) ($item['subtotal'] ?? 0);
        });

        // Retrieve global invoice discount inputs
        $invoiceDiscountType = DiscountType::toString($get($prefix.'discount_type'));

        // If the global discount type is cleared, wipe out the discount amount
        if (blank($invoiceDiscountType)) {
            $set($prefix.'discount_amount', null);
        }

        $invoiceDiscountAmount = (float) ($get($prefix.'discount_amount') ?? 0);

        $globalDiscountAmount = 0.0;
        if ($invoiceDiscountType == DiscountType::Fixed->value) {
            // Cap the fixed global discount so it never exceeds the sum of all item line totals
            $globalDiscountAmount = min($invoiceDiscountAmount, $initialLinesTotalSum);
        } elseif ($invoiceDiscountType == DiscountType::Percentage->value) {
            // Cap the percentage at 100%, then calculate its monetary value based on the summed line totals
            $invoiceDiscountAmount = min($invoiceDiscountAmount, 100);
            $globalDiscountAmount = $initialLinesTotalSum * ($invoiceDiscountAmount / 100);
        }

        // Sum up the monetary value of all individual item-level discounts
        $itemDiscountsSum = collect($items)->sum(function ($item) {
            return (float) ($item['line_total_discount'] ?? 0);
        });

        $shippingCost = (float) ($get($prefix.'shipping_cost') ?? 0);

        // The final payable amount is the sum of item line totals minus the global invoice discount plus shipping cost
        $totalAmount = ($initialLinesTotalSum - $globalDiscountAmount) + $shippingCost;

        // The grand total discount is the aggregate of all item discounts PLUS the global invoice discount
        $grandTotalDiscount = $itemDiscountsSum + $globalDiscountAmount;

        // Update the Livewire component state with rounded final values
        $set($prefix.'subtotal', round($initialSubtotalsSum, 2));
        $set($prefix.'global_discount_amount', round($globalDiscountAmount, 2));
        $set($prefix.'grand_total_discount', round($grandTotalDiscount, 2));
        $set($prefix.'total_amount', round($totalAmount, 2));
    }

    /**
     * Calculates the absolute minimum allowed total for the entire basket
     * based on each item's variant minimum price.
     */
    protected static function getMinimumAllowedBasketTotal(array $items): float
    {
        $sum = 0.0;

        foreach ($items as $item) {
            if (! ($variant = self::getCachedVariant((int) ($item['product_variant_id'] ?? 0))) ||
                ! ($priceTypeEnum = PriceType::try($item['price_type'] ?? null))
            ) {
                continue;
            }

            $minPrice = $variant->getMinimumAllowedPrice($priceTypeEnum);
            $quantity = (float) ($item['quantity'] ?? 1);

            $sum += $minPrice * $quantity;
        }

        return $sum;
    }

    /**
     * Returns a cached ProductVariant for the given ID, avoiding repeated
     * DB hits from multiple closure callbacks on the same repeater row.
     *
     * Uses a static array (request-scoped) so each variant is fetched at
     * most once per Livewire render cycle.
     */
    private static function getCachedVariant(?int $variantId): ?ProductVariant
    {
        if (! $variantId) {
            return null;
        }

        if (app()->runningUnitTests()) {
            return ProductVariant::find($variantId);
        }

        static $cache = [];

        return $cache[$variantId] ??= ProductVariant::find($variantId);
    }
}
