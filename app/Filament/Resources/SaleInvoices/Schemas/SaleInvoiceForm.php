<?php

namespace App\Filament\Resources\SaleInvoices\Schemas;

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
                                ->columnSpan(2),

                            Select::make('price_type')
                                ->label(__('sale_invoice.price_type'))
                                ->options(PriceType::class)
                                ->default(PriceType::Retail->value)
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

                                    $priceType = $state instanceof PriceType ? $state->value : $state;

                                    if ($priceType === PriceType::Wholesale->value) {
                                        $set('unit_price', (float) $variant->wholesale_price);
                                    } else {
                                        $set('unit_price', (float) $variant->retail_price);
                                    }
                                    self::recalculateLine($get, $set);
                                    self::calcTotalAmount($get, $set, '../../');
                                })
                                ->columnSpan(1),

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
                                    $priceTypeValue = $priceType instanceof PriceType ? $priceType->value : $priceType;

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
                                    $priceTypeValue = $priceType instanceof PriceType ? $priceType->value : $priceType;

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
                                            $priceTypeValue = $priceType instanceof PriceType ? $priceType->value : $priceType;

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
                                ->columnSpan(2),

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
                                    $priceTypeValue = $priceType instanceof PriceType ? $priceType->value : $priceType;

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
                                    $priceTypeValue = $priceType instanceof PriceType ? $priceType->value : $priceType;

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
                                    $priceTypeValue = $priceType instanceof PriceType ? $priceType->value : $priceType;

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

                            TextInput::make('line_total')
                                ->label(__('sale_invoice.line_total'))
                                ->numeric()
                                ->readOnly()
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('sale_invoice.line_total_tooltip'))
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->columnSpan(2),

                            Textarea::make('notes')
                                ->label(__('sale_invoice.item_notes'))
                                ->maxLength(255)
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('sale_invoice.notes_tooltip'))
                                ->columnSpan(2),
                        ])
                        ->columns(9)
                        ->addable(false)
                        ->reorderable(false)
                        ->cloneable(false)
                        ->defaultItems(0)
                        ->deleteAction(
                            fn ($action) => $action->after(fn (Get $get, Set $set) => self::calcTotalAmount($get, $set))
                        ),

                    TextInput::make('total_amount')
                        ->label(__('sale_invoice.total_amount'))
                        ->readOnly()
                        ->numeric()
                        ->extraInputAttributes(['class' => 'text-xl font-bold'])
                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            static::calcTotalAmount($get, $set);
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function recalculateLine(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);

        $subtotal = round($quantity * $unitPrice, 2);
        $taxAmount = 0.0; // TAX FEATURE POSTPONED
        $lineTotal = round($subtotal + $taxAmount, 2);

        $set('subtotal', $subtotal);
        $set('line_total', $lineTotal);
    }

    private static function calcTotalAmount(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix.'items') ?? [];
        $total = collect($items)->sum('line_total');
        $set($prefix.'total_amount', round($total, 2));
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
