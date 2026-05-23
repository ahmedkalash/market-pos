<?php

namespace App\Filament\Resources\SaleInvoices\Schemas;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
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
            Section::make(__('sale_invoice.sale_invoice'))
                ->icon('heroicon-o-document-arrow-up')
                ->schema([
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
                                'discount_amount' => null,
                                'line_total' => round(1 * $unitPrice, 2),
                                'notes' => null,
                            ];

                            $set('items', $items);
                            self::calcTotalAmount($get, $set);

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
                                ->default(PriceType::Retail->value)
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('sale_invoice.price_type_helper_text'))
                                ->required()
                                ->disableOptionWhen(function (string $value, Get $get) {
                                    if ($value === PriceType::Wholesale->value) {
                                        $variantId = $get('product_variant_id');
                                        if (! $variantId) {
                                            return true;
                                        }

                                        return ! self::getCachedVariant((int) $variantId)?->wholesale_enabled;
                                    }

                                    return false;
                                })
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    $variantId = $get('product_variant_id');
                                    if (! $variantId) {
                                        return;
                                    }
                                    $variant = self::getCachedVariant((int) $variantId);
                                    if (! $variant) {
                                        return;
                                    }

                                    $priceType = PriceType::parseValue($state);

                                    if ($priceType == PriceType::Wholesale->value) {
                                        $set('unit_price', (float) $variant->wholesale_price);
                                    } else {
                                        $set('unit_price', (float) $variant->retail_price);
                                    }
                                    self::recalculateLine($get, $set);
                                    self::calcTotalAmount($get, $set, '../../');
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
                                        $variantId = $get('product_variant_id');
                                        if ($variantId) {
                                            $variant = self::getCachedVariant((int) $variantId);
                                            if ($variant && $variant->hasWholesaleQtyThreshold()) {
                                                return __('sale_invoice.wholesale_qty_tooltip', ['qty' => (float) $variant->wholesale_qty_threshold]);
                                            }
                                        }

                                        return __('sale_invoice.quantity_tooltip');
                                    }
                                )
                                ->suffix(function (Get $get) {
                                    $priceType = $get('price_type');
                                    $priceTypeValue = PriceType::parseValue($priceType);

                                    if ($priceTypeValue === PriceType::Wholesale->value) {
                                        $variantId = $get('product_variant_id');
                                        if ($variantId) {
                                            $variant = self::getCachedVariant((int) $variantId);
                                            if ($variant && $variant->hasWholesaleQtyThreshold()) {
                                                return __('app.min').': '.(float) $variant->wholesale_qty_threshold;
                                            }
                                        }
                                    }

                                    return null;
                                })
                                ->helperText(function (Get $get) {
                                    $priceType = $get('price_type');
                                    $priceTypeValue = PriceType::parseValue($priceType);

                                    if ($priceTypeValue === PriceType::Wholesale->value) {
                                        $variantId = $get('product_variant_id');
                                        if ($variantId) {
                                            $variant = self::getCachedVariant((int) $variantId);
                                            if ($variant && $variant->hasWholesaleQtyThreshold()) {
                                                return new HtmlString('<span class="text-danger-600 dark:text-danger-400 font-medium">'.__('sale_invoice.wholesale_qty_tooltip', ['qty' => (float) $variant->wholesale_qty_threshold]).'</span>');
                                            }
                                        }
                                    }

                                    return null;
                                })
                                ->disabled(fn (Get $get) => ! $get('product_variant_id'))
                                ->rules([
                                    function (Get $get) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $priceType = $get('price_type');
                                            $priceTypeValue = PriceType::parseValue($priceType);

                                            if ($priceTypeValue === PriceType::Wholesale->value) {
                                                $variantId = $get('product_variant_id');
                                                if ($variantId) {
                                                    $variant = self::getCachedVariant((int) $variantId);
                                                    if ($variant && $variant->hasWholesaleQtyThreshold() && $value < $variant->wholesale_qty_threshold) {
                                                        $fail(__('sale_invoice.wholesale_qty_tooltip', ['qty' => (float) $variant->wholesale_qty_threshold]));
                                                    }
                                                }
                                            }
                                        };
                                    },
                                ])
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::recalculateLine($get, $set);
                                    self::calcTotalAmount($get, $set, '../../');
                                })
                                ->columnSpan(3),

                            TextInput::make('unit_price')
                                ->label(__('sale_invoice.unit_price'))
                                ->numeric()
                                ->required()
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->minValue(function (Get $get) {
                                    $variantId = $get('product_variant_id');
                                    if (! $variantId) {
                                        return 0;
                                    }
                                    $variant = self::getCachedVariant((int) $variantId);
                                    if (! $variant) {
                                        return 0;
                                    }

                                    $priceType = $get('price_type');
                                    $priceTypeValue = PriceType::parseValue($priceType);

                                    if ($priceTypeValue === PriceType::Wholesale->value) {
                                        return $variant->wholesale_is_price_negotiable
                                            ? (float) $variant->min_wholesale_price
                                            : (float) $variant->wholesale_price;
                                    }

                                    return $variant->retail_is_price_negotiable
                                        ? (float) $variant->min_retail_price
                                        : (float) $variant->retail_price;
                                })
                                ->step(0.01)
                                ->helperText(function (Get $get) use ($user) {
                                    $variantId = $get('product_variant_id');
                                    if (! $variantId) {
                                        return null;
                                    }
                                    $variant = self::getCachedVariant((int) $variantId);
                                    if (! $variant) {
                                        return null;
                                    }

                                    $priceType = $get('price_type');
                                    $priceTypeValue = PriceType::parseValue($priceType);

                                    $isNegotiable = ($priceTypeValue == PriceType::Wholesale->value
                                        ? $variant->wholesale_is_price_negotiable
                                        : $variant->retail_is_price_negotiable);

                                    if (! $isNegotiable) {
                                        return __('sale_invoice.non_negotiable');
                                    }

                                    $minPrice = $priceTypeValue === PriceType::Wholesale->value
                                        ? (float) $variant->min_wholesale_price
                                        : (float) $variant->min_retail_price;

                                    $decimalPrecision = (int) ($user->company->decimal_precision ?? 2);
                                    $decimalSeparator = $user->company->decimal_separator ?? '.';
                                    $thousandSeparator = $user->company->thousand_separator ?? ',';
                                    $formattedPrice = number_format($minPrice, $decimalPrecision, $decimalSeparator, $thousandSeparator);

                                    return __('app.min').': '.$formattedPrice;
                                })
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('sale_invoice.unit_price_tooltip'))
                                ->disabled(fn (Get $get) => ! $get('product_variant_id'))
                                ->readOnly(function (Get $get) {
                                    $variantId = $get('product_variant_id');
                                    if (! $variantId) {
                                        return true;
                                    }
                                    $variant = self::getCachedVariant((int) $variantId);
                                    if (! $variant) {
                                        return true;
                                    }

                                    $priceType = $get('price_type');
                                    $priceTypeValue = PriceType::parseValue($priceType);

                                    return $priceTypeValue === PriceType::Wholesale->value
                                        ? ! $variant->wholesale_is_price_negotiable
                                        : ! $variant->retail_is_price_negotiable;
                                })
                                ->live(debounce: '500ms')
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::recalculateLine($get, $set);
                                    self::calcTotalAmount($get, $set, '../../');
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
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::recalculateLine($get, $set);
                                    self::calcTotalAmount($get, $set, '../../');
                                })
                                ->columnSpan(3),

                            TextInput::make('unit_discount_amount')
                                ->label(__('sale_invoice.unit_discount_amount'))
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->helperText(fn (Get $get) => self::isDiscountDisabled($get) ? __('sale_invoice.discount_disabled_helper_text') : __('sale_invoice.unit_discount_helper_text'))
                                ->disabled(fn (Get $get) => self::isDiscountDisabled($get))
                                ->prefix(function (TextInput $component) use ($user) {
                                    $discountTypeStatePath = str_replace($component->getName(), 'discount_type', $component->getStatePath());
                                    $currency = addslashes($user->company->currency_symbol ?? 'ج.م');
                                    $percentage = DiscountType::Percentage->value;

                                    return new HtmlString(
                                        '<span x-data="{}" x-text="$wire.get(\''.$discountTypeStatePath.'\') === \''.$percentage.'\' ? \'%\' : \''.$currency.'\'"></span>'
                                    );
                                })
                                ->live(debounce: '500ms')
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::recalculateLine($get, $set);
                                    self::calcTotalAmount($get, $set, '../../');
                                })
                                ->rules([
                                    function (Get $get) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $value = (float) $value;
                                            $variantId = $get('product_variant_id');
                                            $discountType = $get('discount_type');
                                            $discountType = DiscountType::parseValue($discountType);
                                            $priceType = $get('price_type');
                                            if (! $variantId || ! $discountType || empty($value)) {
                                                return;
                                            }

                                            $variant = self::getCachedVariant((int) $variantId);
                                            if (! $variant) {
                                                return;
                                            }

                                            $priceTypeValue = PriceType::parseValue($priceType);
                                            $isNegotiable = ($priceTypeValue == PriceType::Wholesale->value ? $variant->wholesale_is_price_negotiable : $variant->retail_is_price_negotiable);
                                            $minPrice = (float) ($priceTypeValue == PriceType::Wholesale->value ? $variant->min_wholesale_price : $variant->min_retail_price);

                                            if (! $isNegotiable) {
                                                $fail(__('sale_invoice.item_not_negotiable', ['item' => $variant->name ?? '']));

                                                return;
                                            }

                                            $unitPrice = (float) ($get('unit_price') ?? 0);

                                            $unitPriceAfterItemDiscount = $discountType === DiscountType::Fixed->value
                                                ? $unitPrice - $value
                                                : $unitPrice - ($unitPrice * ($value / 100));

                                            if (round($unitPriceAfterItemDiscount, 2) < round($minPrice, 2)) {
                                                $fail(__('sale_invoice.item_below_minimum', ['item' => $variant->name ?? '', 'min' => $minPrice]));
                                            }
                                        };
                                    },
                                ])
                                ->columnSpan(3),

                            TextInput::make('line_total_discount')
                                ->label(__('sale_invoice.line_total_discount'))
                                ->numeric()
                                ->readOnly()
                                ->dehydrated(false)
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->helperText(fn (Get $get) => self::isDiscountDisabled($get) ? __('sale_invoice.discount_disabled_helper_text') : __('sale_invoice.line_total_discount_helper_text'))
                                ->columnSpan(3)
                                ->disabled(fn (Get $get) => self::isDiscountDisabled($get)),

                            TextInput::make('line_total')
                                ->label(__('sale_invoice.final_line_total'))
                                ->numeric()
                                ->readOnly()
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->helperText(__('sale_invoice.final_line_total_helper_text'))
                                ->columnSpan(4),

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
                            fn ($action) => $action->after(fn (Get $get, Set $set) => self::calcTotalAmount($get, $set))
                        ),

                    Section::make(__('sale_invoice.invoice_discount'))
                        ->icon('heroicon-o-receipt-percent')
                        ->schema([
                            Select::make('discount_type')
                                ->label(__('sale_invoice.discount_type'))
                                ->options(DiscountType::class)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    static::calcTotalAmount($get, $set);
                                }),
                            TextInput::make('discount_amount')
                                ->label(__('sale_invoice.discount_amount'))
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->prefix(function (TextInput $component) use ($user) {
                                    $discountTypeStatePath = str_replace($component->getName(), 'discount_type', $component->getStatePath());
                                    $currency = addslashes($user->company->currency_symbol ?? 'ج.م');
                                    $percentage = DiscountType::Percentage->value;

                                    return new HtmlString(
                                        '<span x-data="{}" x-text="$wire.get(\''.$discountTypeStatePath.'\') === \''.$percentage.'\' ? \'%\' : \''.$currency.'\'"></span>'
                                    );
                                })
                                ->live(debounce: '500ms')
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    static::calcTotalAmount($get, $set);
                                })
                                ->rules([
                                    function (Get $get) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $discountType = $get('discount_type');
                                            $discountType = DiscountType::parseValue($discountType);
                                            $items = $get('items') ?? [];
                                            if (! $discountType || empty($value) || empty($items)) {
                                                return;
                                            }

                                            $initialSubtotalsSum = 0.0;
                                            foreach ($items as $item) {
                                                $unitPrice = (float) ($item['unit_price'] ?? 0);
                                                $quantity = (float) ($item['quantity'] ?? 1);
                                                $itemDiscountType = $item['discount_type'] ?? null;
                                                $itemDiscountType = DiscountType::parseValue($itemDiscountType);
                                                $itemDiscountAmount = (float) ($item['unit_discount_amount'] ?? 0);
                                                $itemDiscountValue = 0.0;
                                                if ($itemDiscountType && $itemDiscountAmount > 0) {
                                                    $itemDiscountValue = $itemDiscountType === DiscountType::Fixed->value
                                                        ? $itemDiscountAmount * $quantity
                                                        : ($unitPrice * $quantity) * ($itemDiscountAmount / 100);
                                                }
                                                $initialSubtotalsSum += (($unitPrice * $quantity) - $itemDiscountValue);
                                            }

                                            $discountAmount = (float) $value;
                                            $totalInvoiceDiscount = $discountType == DiscountType::Fixed->value
                                                ? $discountAmount
                                                : $initialSubtotalsSum * ($discountAmount / 100);

                                            foreach ($items as $item) {
                                                $variantId = $item['product_variant_id'] ?? null;
                                                if (! $variantId) {
                                                    continue;
                                                }
                                                $variant = self::getCachedVariant((int) $variantId);
                                                if (! $variant) {
                                                    continue;
                                                }

                                                $priceType = $item['price_type'] ?? null;
                                                $priceTypeValue = PriceType::parseValue($priceType);

                                                $isNegotiable = ($priceTypeValue == PriceType::Wholesale->value ? $variant->wholesale_is_price_negotiable : $variant->retail_is_price_negotiable);
                                                $minPrice = (float) ($priceTypeValue == PriceType::Wholesale->value ? $variant->min_wholesale_price : $variant->min_retail_price);
                                                $unitPrice = (float) ($item['unit_price'] ?? 0);
                                                $quantity = (float) ($item['quantity'] ?? 1);

                                                $minimumAllowedSubtotal = $isNegotiable ? ($minPrice * $quantity) : ($unitPrice * $quantity);

                                                $itemDiscountType = $item['discount_type'] ?? null;
                                                $itemDiscountType = DiscountType::parseValue($itemDiscountType);
                                                $itemDiscountAmount = (float) ($item['unit_discount_amount'] ?? 0);
                                                $itemDiscountValue = 0.0;
                                                if ($itemDiscountType && $itemDiscountAmount > 0) {
                                                    $unitDiscountValue = $itemDiscountType === DiscountType::Fixed->value
                                                        ? $itemDiscountAmount
                                                        : $unitPrice * ($itemDiscountAmount / 100);
                                                    $itemDiscountValue = $unitDiscountValue * $quantity;
                                                }
                                                $initialSubtotal = (($unitPrice * $quantity) - $itemDiscountValue);

                                                $distributedInvoiceDiscount = 0.0;
                                                if ($initialSubtotalsSum > 0 && $totalInvoiceDiscount > 0) {
                                                    $distributedInvoiceDiscount = ($initialSubtotal / $initialSubtotalsSum) * $totalInvoiceDiscount;
                                                }

                                                $finalSubtotal = $initialSubtotal - $distributedInvoiceDiscount;

                                                if (round($finalSubtotal, 2) < round($minimumAllowedSubtotal, 2)) {
                                                    $fail(__('sale_invoice.invoice_discount_breaches_minimum', ['item' => $variant->name ?? '']));

                                                    return;
                                                }
                                            }
                                        };
                                    },
                                ]),
                            TextInput::make('total_discount_amount')
                                ->label(__('sale_invoice.total_discount_amount'))
                                ->readOnly()
                                ->dehydrated()
                                ->numeric()
                                ->prefix($user->company->currency_symbol ?? 'ج.م'),
                        ])->columns(3),

                    TextInput::make('total_amount')
                        ->label(__('sale_invoice.total_amount'))
                        ->readOnly()
                        ->dehydrated()
                        ->numeric()
                        ->extraInputAttributes(['class' => 'text-xl font-bold text-success-600 dark:text-success-400'])
                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            static::calcTotalAmount($get, $set);
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function isDiscountDisabled(Get $get): bool
    {
        $variantId = $get('product_variant_id');
        $priceType = $get('price_type');
        $priceType = PriceType::parseValue($priceType);

        if (! $variantId || ! $priceType) {
            return false;
        }
        $variant = self::getCachedVariant((int) $variantId);
        if (! $variant) {
            return false;
        }

        if ($priceType == PriceType::Wholesale->value) {
            return ! $variant->wholesale_is_price_negotiable;
        }

        return ! $variant->retail_is_price_negotiable;
    }

    private static function recalculateLine(Get $get, Set $set): void
    {
        $variantId = $get('product_variant_id');
        $priceType = $get('price_type');

        $isNegotiable = true;
        if ($variantId && $priceType) {
            $variant = self::getCachedVariant((int) $variantId);
            if ($variant) {
                $isNegotiable = ($priceType == PriceType::Wholesale->value)
                    ? $variant->wholesale_is_price_negotiable
                    : $variant->retail_is_price_negotiable;
            }
        }

        if (! $isNegotiable) {
            $set('discount_type', null);
            $set('unit_discount_amount', null);
            $set('line_total_discount', null);
        }

        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discountType = DiscountType::parseValue($get('discount_type'));

        $discountAmount = (float) ($get('unit_discount_amount') ?? 0);

        $discountValue = 0.0;
        if ($discountType == DiscountType::Fixed->value) {
            $discountValue = $discountAmount * $quantity;
        } elseif ($discountType == DiscountType::Percentage->value) {
            $discountValue = ($unitPrice * $quantity) * ($discountAmount / 100);
        }

        $subtotalBeforeDiscount = $unitPrice * $quantity;
        $subtotalAfterDiscount = $subtotalBeforeDiscount - $discountValue;

        $lineTotal = round($subtotalAfterDiscount, 2);

        $set('subtotal', round($subtotalBeforeDiscount, 2));
        $set('line_total_discount', round($discountValue, 2));
        $set('line_total', $lineTotal);
    }

    private static function calcTotalAmount(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix.'items') ?? [];
        $initialSubtotalsSum = collect($items)->sum(function ($item) {
            return (float) ($item['subtotal'] ?? 0) - (float) ($item['line_total_discount'] ?? 0);
        });

        $invoiceDiscountType = DiscountType::parseValue( $get($prefix.'discount_type'));
        $invoiceDiscountAmount = (float) ($get($prefix.'discount_amount') ?? 0);

        $totalInvoiceDiscount = 0.0;
        if ($invoiceDiscountType == DiscountType::Fixed->value) {
            $totalInvoiceDiscount = $invoiceDiscountAmount;
        } elseif ($invoiceDiscountType == DiscountType::Percentage->value) {
            $totalInvoiceDiscount = $initialSubtotalsSum * ($invoiceDiscountAmount / 100);
        }

        $totalAmount = $initialSubtotalsSum - $totalInvoiceDiscount;

        $set($prefix.'total_discount_amount', round($totalInvoiceDiscount, 2));
        $set($prefix.'total_amount', round($totalAmount, 2));
    }

    /**
     * Returns a cached ProductVariant for the given ID, avoiding repeated
     * DB hits from multiple closure callbacks on the same repeater row.
     *
     * Uses a static array (request-scoped) so each variant is fetched at
     * most once per Livewire render cycle.
     */
    private static function getCachedVariant(int $variantId): ?ProductVariant
    {
        static $cache = [];

        return $cache[$variantId] ??= ProductVariant::find($variantId);
    }
}
