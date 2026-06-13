<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Enums\DiscountType;
use App\Enums\ExtraItemActionType;
use App\Models\InvoiceExtraItemPreset;
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
                        ->columns(fn () => $user->isCompanyLevel() ? 5 : 4)
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

                    Section::make(__('app.main_invoice_items'))
                        ->footer(function (Get $get) use ($user) {
                            $items = $get('items') ?? [];
                            if (empty($items)) {
                                return null;
                            }

                            $itemsSubtotalsSum = number_format($get('subtotal'), 2);
                            $itemsLinesTotalsSum = number_format($get('items_lines_totals'), 2);
                            $currency = $user->company->currency_symbol ?? 'ج.م';

                            return new HtmlString(
                                "<div style='display: flex; gap: 1.5rem; margin-top: 0.5rem;'>".
                                badge(__('purchase_invoice.items_section_subtotal').' : '.$itemsSubtotalsSum.' '.$currency).
                                badge(__('purchase_invoice.items_section_line_total').' : '.$itemsLinesTotalsSum.' '.$currency).
                                '</div>'
                            );
                        })
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
                                    if (! $state) {
                                        return;
                                    }
                                    $set('barcode_scanner', null);

                                    $variant = ProductVariant::with(['product.taxClass', 'barcodes'])
                                        ->whereHas('barcodes', fn ($q) => $q->where('barcode', $state))
                                        ->first();

                                    if (! $variant) {
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
                                    if (! $state) {
                                        return;
                                    }

                                    // Clear the select field so it can be used again
                                    $set('product_search', null);

                                    $variant = ProductVariant::with(['product.taxClass', 'barcodes'])
                                        ->find($state);

                                    if (! $variant) {
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

                                    return new HtmlString("<div class='flex items-center'>$productHtml<span class='text-sm text-gray-500' style='margin-inline-end: 0.5rem;'>".__('purchase_invoice.barcode').":</span>$badgesHtml</div>");
                                })
                                ->hiddenLabel()
                                ->compact()
                                ->columns(4)
                                ->schema([
                                    Hidden::make('product_variant_id')->required(),

                                    TextInput::make('quantity')
                                        ->label(__('purchase_invoice.quantity'))
                                        ->numeric()
                                        ->required()
                                        ->default(1)
                                        ->step(1)
                                        ->helperText(__('purchase_invoice.quantity_tooltip'))
                                        ->disabled(fn (Get $get) => ! $get('product_variant_id'))
                                        ->live()
                                        ->rules(['min:0.001'])
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::recalculateLine($get, $set);
                                            self::recalculateTotals($get, $set, '../../');
                                        })
                                        ->columnSpan(1),

                                    TextInput::make('unit_cost')
                                        ->label(__('purchase_invoice.unit_cost'))
                                        ->numeric()
                                        ->required()
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->minValue(0)
                                        ->step(1)
                                        ->helperText(__('purchase_invoice.unit_cost_tooltip'))
                                        ->disabled(fn (Get $get) => ! $get('product_variant_id'))
                                        ->live(debounce: 1000)
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::recalculateLine($get, $set);
                                            self::recalculateTotals($get, $set, '../../');
                                        })
                                        ->columnSpan(1),

                                    TextInput::make('subtotal')
                                        ->label(__('purchase_invoice.subtotal'))
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated()
                                        ->helperText(__('purchase_invoice.subtotal_tooltip'))
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->columnSpan(1),

                                    Textarea::make('notes')
                                        ->label(__('purchase_invoice.item_notes'))
                                        ->maxLength(255)
                                        ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_invoice.notes_tooltip'))
                                        ->rows(1)
                                        ->columnSpan(1),

                                    Select::make('discount_type')
                                        ->label(__('purchase_invoice.discount_type'))
                                        ->options(DiscountType::class)
                                        ->helperText(__('purchase_invoice.discount_type_helper_text'))
                                        ->dehydrated()
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set, $livewire, Select $component) {
                                            self::recalculateLine($get, $set);
                                            self::recalculateTotals($get, $set, '../../');
                                            $unitDiscountAmountPath = str_replace($component->getName(), 'unit_discount_amount', $component->getStatePath());
                                            $livewire->validateOnly($unitDiscountAmountPath);
                                        })
                                        ->columnSpan(1),

                                    TextInput::make('unit_discount_amount')
                                        ->label(__('purchase_invoice.unit_discount_amount'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->required(fn (Get $get) => filled($get('discount_type')))
                                        ->helperText(__('purchase_invoice.unit_discount_helper_text'))
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
                                        ->hint(function (Get $get) {
                                            $discountType = DiscountType::try($get('discount_type'));
                                            if (! $discountType) {
                                                return null;
                                            }

                                            $unitCost = (float) ($get('unit_cost') ?? 0);

                                            if ($discountType === DiscountType::Percentage) {
                                                return __('purchase_invoice.max_allowed_discount', ['max' => '100%']);
                                            }

                                            return __('purchase_invoice.max_allowed_discount', ['max' => round($unitCost, 2)]);
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
                                                    $discountType = DiscountType::try($get('discount_type'));
                                                    if (! $discountType || empty($value = (float) $value)) {
                                                        return;
                                                    }

                                                    if ($discountType === DiscountType::Percentage && $value > 100) {
                                                        $fail(__('purchase_invoice.percentage_exceeds_100'));

                                                        return;
                                                    }

                                                    $unitCost = (float) ($get('unit_cost') ?? 0);

                                                    if ($discountType === DiscountType::Fixed && round($value, 2) > round($unitCost, 2)) {
                                                        $fail(__('purchase_invoice.discount_exceeds_unit_cost', ['max' => $unitCost]));
                                                    }
                                                };
                                            },
                                        ])
                                        ->columnSpan(1),

                                    TextInput::make('line_total_discount')
                                        ->label(__('purchase_invoice.line_total_discount'))
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated()
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->helperText(__('purchase_invoice.line_total_discount_helper_text'))
                                        ->columnSpan(1),

                                    TextInput::make('line_total')
                                        ->label(__('purchase_invoice.final_line_total'))
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated()
                                        ->minValue(0)
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->helperText(__('purchase_invoice.final_line_total_helper_text'))
                                        ->columnSpan(1),
                                ])

                                ->addable(false)
                                ->reorderable(false)
                                ->cloneable(false)
                                ->defaultItems(0)
                                ->deleteAction(
                                    fn ($action) => $action->after(fn (Get $get, Set $set) => self::recalculateTotals($get, $set))
                                ),

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
                                        ->options((InvoiceExtraItemPreset::query()->forPurchaseInvoice()->active()->pluck('name', 'id')))
                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                            $preset = InvoiceExtraItemPreset::find((int) $state);
                                            if ($preset) {
                                                $set('name', $preset->name);
                                                $set('action_type', $preset->action_type);
                                                $set('amount', $preset->amount);
                                            }
                                            static::recalculateTotals($get, $set, '../../');
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
                                            static::recalculateTotals($get, $set, '../../');
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
                                            static::recalculateTotals($get, $set, '../../');
                                        }),
                                    Textarea::make('notes')
                                        ->label(__('app.notes'))
                                        ->helperText(__('purchase_invoice.extra_notes_tooltip'))
                                        ->rows(1)
                                        ->maxLength(255),
                                ])
                                ->deleteAction(
                                    fn (Action $action) => $action->after(fn (Get $get, Set $set) => static::recalculateTotals($get, $set))
                                )
                                ->columns(5),
                        ]),

                    Section::make(__('purchase_invoice.invoice_discount'))
                        ->description(__('purchase_invoice.invoice_discount_description'))
                        ->columnSpanFull()
                        ->icon('heroicon-o-receipt-percent')
                        ->schema([
                            Select::make('discount_type')
                                ->label(__('purchase_invoice.discount_type'))
                                ->helperText(__('purchase_invoice.global_discount_type_helper'))
                                ->options(DiscountType::class)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, $livewire, Select $component) {
                                    static::recalculateTotals($get, $set);

                                    $discountAmountPath = str_replace($component->getName(), 'discount_amount', $component->getStatePath());
                                    $livewire->validateOnly($discountAmountPath);
                                }),
                            TextInput::make('discount_amount')
                                ->label(__('purchase_invoice.global_discount_amount'))
                                ->helperText(__('purchase_invoice.global_discount_amount_helper'))
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
                                    if (! ($discountType = DiscountType::try($get('discount_type'))) || empty($get('items') ?? [])) {
                                        return null;
                                    }

                                    $initialLinesTotalSum = $get('items_lines_totals');

                                    if ($initialLinesTotalSum <= 0) {
                                        return __('purchase_invoice.max_allowed_discount', ['max' => '0']);
                                    }

                                    if ($discountType === DiscountType::Percentage) {
                                        return __('purchase_invoice.max_allowed_discount', ['max' => '100%']);
                                    }

                                    return __('purchase_invoice.max_allowed_discount', ['max' => round($initialLinesTotalSum, 2)]);
                                })
                                ->live(debounce: 1000)
                                ->afterStateUpdated(function (Get $get, Set $set, $livewire, TextInput $component) {
                                    static::recalculateTotals($get, $set);
                                    $livewire->validateOnly($component->getStatePath());
                                })
                                ->rules([
                                    function (Get $get) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $discountType = DiscountType::try($get('discount_type'));
                                            $items = $get('items') ?? [];
                                            if (! $discountType || empty($value) || empty($items)) {
                                                return;
                                            }

                                            if ($discountType === DiscountType::Percentage && (float) $value > 100) {
                                                $fail(__('purchase_invoice.percentage_exceeds_100'));

                                                return;
                                            }

                                            $initialLinesTotalSum = $get('items_lines_totals');

                                            if ($discountType === DiscountType::Fixed &&
                                                round((float) $value, 2) > round($initialLinesTotalSum, 2)) {
                                                $fail(__('purchase_invoice.grand_total_discount_exceeds_total'));

                                                return;
                                            }
                                        };
                                    },
                                ]),
                            TextInput::make('global_discount_amount')
                                ->label(__('purchase_invoice.global_discount_amount'))
                                ->disabled()
                                ->dehydrated()
                                ->numeric()
                                ->prefix($user->company->currency_symbol ?? 'ج.م'),
                        ])
                        ->columns(3)
                        ->compact(),

                    Section::make(__('app.summary'))
                        ->compact()
                        ->columnSpanFull()
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Grid::make(4)
                                ->columnSpanFull()
                                ->schema([
                                    TextInput::make('subtotal')
                                        ->label(__('purchase_invoice.items_subtotal'))
                                        ->helperText(__('purchase_invoice.subtotal_amount_helper'))
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
                                    TextInput::make('grand_total_discount')
                                        ->label(__('purchase_invoice.grand_total_discount'))
                                        ->disabled()
                                        ->dehydrated()
                                        ->numeric()
                                        ->extraInputAttributes(['class' => 'text-lg font-semibold text-danger-600 dark:text-danger-400'])
                                        ->helperText(__('purchase_invoice.grand_total_discount_helper'))
                                        ->prefix($user->company->currency_symbol ?? 'ج.م'),
                                    TextInput::make('total_amount')
                                        ->label(__('purchase_invoice.total_amount'))
                                        ->helperText(__('purchase_invoice.total_amount_helper'))
                                        ->disabled()
                                        ->dehydrated()
                                        ->minValue(0)
                                        ->extraInputAttributes(['class' => 'text-xl font-bold text-success-600 dark:text-success-400'])
                                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                                        ->afterStateHydrated(function (Get $get, Set $set) {
                                            static::recalculateTotals($get, $set);
                                        }),
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
        $quantity = (float) ($get('quantity') ?? 0);
        $unitCost = (float) ($get('unit_cost') ?? 0);
        $subtotal = round($quantity * $unitCost, 2);

        $discountType = DiscountType::try($get('discount_type'));
        $unitDiscountAmount = (float) ($get('unit_discount_amount') ?? 0);

        $monetaryUnitDiscount = 0.0;
        if ($discountType === DiscountType::Percentage) {
            $monetaryUnitDiscount = $unitCost * ($unitDiscountAmount / 100);
        } elseif ($discountType === DiscountType::Fixed) {
            $monetaryUnitDiscount = $unitDiscountAmount;
        }

        $lineTotalDiscount = round($monetaryUnitDiscount * $quantity, 2);
        $lineTotal = round($subtotal - $lineTotalDiscount, 2);

        $set('subtotal', $subtotal);
        $set('line_total_discount', $lineTotalDiscount);
        $set('line_total', $lineTotal);
    }

    private static function recalculateTotals(Get $get, Set $set, string $prefix = ''): void
    {
        $items = $get($prefix.'items') ?? [];
        $itemsSubtotal = collect($items)->sum('subtotal');
        $itemsLinesTotals = collect($items)->sum('line_total');

        $extraItemsTotal = self::calculateExtraItemsTotal($get, $prefix);

        $globalDiscountType = DiscountType::try($get($prefix.'discount_type'));
        $globalDiscountAmountVal = (float) ($get($prefix.'discount_amount') ?? 0);

        $globalDiscountAmount = 0.0;
        if ($globalDiscountType === DiscountType::Percentage) {
            $globalDiscountAmount = $itemsLinesTotals * ($globalDiscountAmountVal / 100);
        } elseif ($globalDiscountType === DiscountType::Fixed) {
            $globalDiscountAmount = $globalDiscountAmountVal;
        }

        $itemsTotalDiscount = collect($items)->sum('line_total_discount');
        $grandTotalDiscount = $itemsTotalDiscount + $globalDiscountAmount;

        $totalAmount = $itemsLinesTotals - $globalDiscountAmount + $extraItemsTotal;

        if ($totalAmount < 0) {
            Notification::make()
                ->warning()
                ->title(__('purchase_invoice.negative_total_warning'))
                ->body(__('purchase_invoice.deductions_exceed_total_message'))
                ->send();
        }

        $set($prefix.'subtotal', round($itemsSubtotal, 2));
        $set($prefix.'items_lines_totals', round($itemsLinesTotals, 2));
        $set($prefix.'global_discount_amount', round($globalDiscountAmount, 2));
        $set($prefix.'grand_total_discount', round($grandTotalDiscount, 2));
        $set($prefix.'extra_items_total', round($extraItemsTotal, 2));
        $set($prefix.'total_amount', round($totalAmount, 2));
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
     * Shared helper to add a variant to the invoice items list.
     * Prevents code duplication between barcode scanner and name search.
     */
    protected static function addVariantToInvoice(ProductVariant $variant, Get $get, Set $set, $livewire): void
    {
        // Check store boundary
        $storeId = $get('store_id');

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

        $fullName = $variant->full_qualified_name;
        $barcodes = $variant->getAllBarcodesAsArray();
        $unitCost = (float) $variant->purchase_price;

        $items[$newKey] = [
            'product_variant_id' => $variant->id,
            'barcodes' => $barcodes,
            'product_name' => $fullName,
            'quantity' => 1,
            'unit_cost' => $unitCost,
            'subtotal' => round($unitCost, 2),
            'line_total_discount' => 0,
            'line_total' => round($unitCost, 2),
            'notes' => null,
        ];

        $set('items', $items);
        self::recalculateTotals($get, $set);

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
                ->default(fn () => $user->store_id)
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->dehydrated(fn (string $operation): bool => $operation !== 'edit');
        }

        return Select::make('store_id')
            ->label(__('purchase_invoice.store'))
            ->relationship('store', lang_suffix('name'))
            ->preload()
            ->required()
            ->searchable(['name_en', 'name_ar'])
            ->live()
            ->visible(fn () => $user->isCompanyLevel())
            ->disabled(fn (string $operation): bool => $operation === 'edit')
            ->dehydrated(fn (string $operation): bool => $operation !== 'edit');
    }
}
