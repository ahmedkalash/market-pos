<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Models\User;
use Auth;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public array $tempVariantAttributes = [];

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('product.variants');
    }

    public static function getModelLabel(): ?string
    {
        return __('product.variant');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('product.variants');
    }

    public function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = auth()->user();
        $companyId = $user->company_id;

        return $schema
            ->components([
                Section::make(__('product.variant_details'))
                    ->compact()
                    ->schema([
                        TextInput::make('name_en')
                            ->label(__('product.variant_name_en'))
                            ->helperText(__('product.variant_name_helper'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_ar')
                            ->label(__('product.variant_name_ar'))
                            ->helperText(__('product.variant_name_helper'))
                            ->required()
                            ->maxLength(255),

                        Select::make('uom_id')
                            ->label(__('unit_of_measure.unit_of_measure'))
                            ->helperText(__('unit_of_measure.uom_helper'))
                            ->relationship('unitOfMeasure', 'name_' . app()->getLocale(), fn(Builder $query) => $query->where('company_id', $companyId))
                            ->required()
                            ->searchable()
                            ->preload(),

                    ])->columns(2),

                Section::make(__('product.inventory'))
                    ->compact()
                    ->schema([
                        TextInput::make('quantity')
                            ->label(__('product.quantity'))
                            ->helperText(__('product.quantity_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        TextInput::make('low_stock_threshold')
                            ->label(__('product.low_stock_threshold'))
                            ->helperText(__('product.low_stock_threshold_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),

                        Toggle::make('is_active')
                            ->label(__('app.active'))
                            ->helperText(__('product.is_active_helper'))
                            ->default(true),
                    ])
                    ->columns(3),

                Section::make(__('product.pricing'))
                    ->compact()
                    ->schema([
                        // --- Purchase / Cost ---
                        TextInput::make('purchase_price')
                            ->label(__('product.purchase_price'))
                            ->helperText(__('product.purchase_price_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix(fn() => $user->company->currency_symbol)
                            ->columnSpanFull(),

                        // --- Retail ---
                        TextInput::make('retail_price')
                            ->label(__('product.retail_price'))
                            ->helperText(__('product.retail_price_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix(fn() => $user->company->currency_symbol),

                        Toggle::make('retail_is_price_negotiable')
                            ->label(__('product.retail_is_price_negotiable'))
                            ->helperText(__('product.retail_is_price_negotiable_helper'))
                            ->live()
                            ->default(false),

                        TextInput::make('min_retail_price')
                            ->label(__('product.min_retail_price'))
                            ->helperText(__('product.min_retail_price_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->required(fn(Get $get) => (bool) $get('retail_is_price_negotiable'))
                            ->prefix(fn() => $user->company->currency_symbol)
                            ->visible(fn(Get $get) => (bool) $get('retail_is_price_negotiable')),

                        // --- Wholesale ---
                        Toggle::make('wholesale_enabled')
                            ->label(__('product.wholesale_enabled'))
                            ->helperText(__('product.wholesale_enabled_helper'))
                            ->live()
                            ->default(false),

                        TextInput::make('wholesale_price')
                            ->label(__('product.wholesale_price'))
                            ->helperText(__('product.wholesale_price_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->required(fn(Get $get) => (bool) $get('wholesale_enabled'))
                            ->prefix(fn() => $user->company->currency_symbol)
                            ->visible(fn(Get $get) => (bool) $get('wholesale_enabled')),

                        TextInput::make('wholesale_qty_threshold')
                            ->label(__('product.wholesale_qty_threshold'))
                            ->helperText(__('product.wholesale_qty_threshold_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->visible(fn(Get $get) => (bool) $get('wholesale_enabled')),

                        Toggle::make('wholesale_is_price_negotiable')
                            ->label(__('product.wholesale_is_price_negotiable'))
                            ->helperText(__('product.wholesale_is_price_negotiable_helper'))
                            ->live()
                            ->default(false)
                            ->visible(fn(Get $get) => (bool) $get('wholesale_enabled')),

                        TextInput::make('min_wholesale_price')
                            ->label(__('product.min_wholesale_price'))
                            ->helperText(__('product.min_wholesale_price_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->required(fn(Get $get) => (bool) $get('wholesale_enabled') && (bool) $get('wholesale_is_price_negotiable'))
                            ->prefix(fn() => $user->company->currency_symbol)
                            ->visible(fn(Get $get) => (bool) $get('wholesale_enabled') && (bool) $get('wholesale_is_price_negotiable')),
                    ])
                    ->columns(2),

                Section::make(__('product.barcodes') . ': ' . __('product.barcode_input_helper'))
                    ->schema([
                        Repeater::make('barcodes')
                            ->hiddenLabel()
                            ->relationship('barcodes')
                            ->schema([
                                TextInput::make('barcode')
                                    ->hiddenLabel()
                                    ->placeholder(__('product.barcode'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('product_barcodes', 'barcode', ignoreRecord: true),
                            ])
                            ->grid(1)
                            ->addActionLabel(__('product.add_barcode'))
                            ->reorderable(false),
                    ])->columnSpan(1)
                    ->compact(),

                Section::make(__('attribute.attributes') . ': ' . __('product.attribute_values_helper'))
                    ->compact()
                    ->schema([
                        Repeater::make('variant_attributes')
                            ->reorderable(false)
                            ->grid(2)
                            ->hiddenLabel()
                            ->schema([
                                Select::make('attribute_id')
                                    ->label(__('attribute.attribute'))
                                    ->helperText(__('attribute.attribute_helper'))
                                    ->options(function () use ($companyId) {
                                        return Attribute::where('company_id', $companyId)->pluck('name_' . app()->getLocale(), 'id');
                                    })
                                    ->live()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name_en')
                                            ->label(__('product.name_english'))
                                            ->required(),
                                        TextInput::make('name_ar')
                                            ->label(__('product.name_arabic'))
                                            ->required(),
                                    ])
                                    ->createOptionUsing(function (array $data) use ($companyId) {
                                        $attribute = Attribute::create([
                                            'company_id' => $companyId,
                                            'name_en' => $data['name_en'],
                                            'name_ar' => $data['name_ar'],
                                        ]);

                                        return $attribute->id;
                                    }),

                                Select::make('attribute_value_id')
                                    ->label(__('attribute.value'))
                                    ->helperText(__('attribute.attribute_value_item_helper'))
                                    ->options(function (Get $get) {
                                        if (! $get('attribute_id')) {
                                            return [];
                                        }

                                        return AttributeValue::where('attribute_id', $get('attribute_id'))->pluck('value_' . app()->getLocale(), 'id');
                                    })
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('value_en')
                                            ->label(__('attribute.value_en'))
                                            ->required(),
                                        TextInput::make('value_ar')
                                            ->label(__('attribute.value_ar'))
                                            ->required(),
                                    ])
                                    ->createOptionUsing(function (array $data, Get $get) {
                                        $value = AttributeValue::create([
                                            'attribute_id' => $get('attribute_id'),
                                            'value_en' => $data['value_en'],
                                            'value_ar' => $data['value_ar'],
                                        ]);

                                        return $value->id;
                                    })
                                    ->disabled(fn(Get $get) => ! $get('attribute_id')),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->addActionLabel(__('attribute.add_attribute')),
                    ])->columnSpanFull(),

            ]);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->recordTitleAttribute('name_' . app()->getLocale())
            ->recordActionsColumnLabel(__('app.actions'))
            ->columns([
                TextColumn::make('name_ar')
                    ->label(__('app.name'))
                    ->description(fn(ProductVariant $record): string => $record->name_en)
                    ->searchable(['name_en', 'name_ar'])
                    ->sortable(),

                TextColumn::make('attributeValues.value_' . app()->getLocale())
                    ->label(__('attribute.attributes'))
                    ->badge()
                    ->color('primary')
                    ->listWithLineBreaks(),

                TextColumn::make('retail_price')
                    ->label(__('product.retail_price'))
                    ->formatStateUsing(fn(?string $state) => $state . ' ' . $user->company->currency_symbol)
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('purchase_price')
                    ->label(__('product.purchase_price'))
                    ->formatStateUsing(fn(?string $state) => $state . ' ' . $user->company->currency_symbol)
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('wholesale_price')
                    ->label(__('product.wholesale_price'))
                    ->formatStateUsing(fn(?string $state) => $state ? $state . ' ' . $user->company->currency_symbol : '—')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('min_retail_price')
                    ->label(__('product.min_retail_price'))
                    ->formatStateUsing(fn(?string $state) => $state ? $state . ' ' . $user->company->currency_symbol : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('min_wholesale_price')
                    ->label(__('product.min_wholesale_price'))
                    ->formatStateUsing(fn(?string $state) => $state ? $state . ' ' . $user->company->currency_symbol : '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('wholesale_qty_threshold')
                    ->label(__('product.wholesale_qty_threshold'))
                    ->numeric(3)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('quantity')
                    ->label(__('product.stock'))
                    ->numeric(2)
                    ->sortable()
                    ->color(fn(ProductVariant $record): ?string => $record->isLowStock() ? 'danger' : null),

                TextColumn::make('unitOfMeasure.abbreviation_' . app()->getLocale())
                    ->label(__('unit_of_measure.uom'))
                    ->badge()
                    ->color('gray'),

                IconColumn::make('retail_is_price_negotiable')
                    ->label(__('product.negotiable'))
                    ->boolean()
                    ->trueIcon(Heroicon::CheckBadge)
                    ->falseIcon(Heroicon::XMark)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('wholesale_is_price_negotiable')
                    ->label(__('product.wholesale_is_price_negotiable'))
                    ->boolean()
                    ->trueIcon(Heroicon::CheckBadge)
                    ->falseIcon(Heroicon::XMark)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('wholesale_enabled')
                    ->label(__('product.wholesale_enabled'))
                    ->boolean()
                    ->trueIcon(Heroicon::CheckBadge)
                    ->falseIcon(Heroicon::XMark)
                    ->toggleable(isToggledHiddenByDefault: true),

                ToggleColumn::make('is_active')
                    ->label(__('app.active')),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('app.active')),

                TernaryFilter::make('retail_is_price_negotiable')
                    ->label(__('product.negotiable')),

                Filter::make('retail_price_range')
                    ->label(__('product.retail_price_range'))
                    //                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Section::make()
                            ->columnSpan('full')
                            //                            ->compact()
                            ->columns()
                            ->schema([
                                TextInput::make('retail_price_from')
                                    ->label(__('product.price_from'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix(fn() => $user->company->currency_symbol),
                                TextInput::make('retail_price_to')
                                    ->label(__('product.price_to'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix(fn() => $user->company->currency_symbol),
                            ])

                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['retail_price_from']),
                                fn(Builder $q) => $q->where('retail_price', '>=', $data['retail_price_from'])
                            )
                            ->when(
                                filled($data['retail_price_to']),
                                fn(Builder $q) => $q->where('retail_price', '<=', $data['retail_price_to'])
                            );
                    })
                    ->indicateUsing(function (array $data) use ($user): array {
                        $indicators = [];
                        $symbol = $user->company->currency_symbol;

                        if (filled($data['retail_price_from'])) {
                            $indicators[] = __('product.price_from') . ': ' . $data['retail_price_from'] . ' ' . $symbol;
                        }

                        if (filled($data['retail_price_to'])) {
                            $indicators[] = __('product.price_to') . ': ' . $data['retail_price_to'] . ' ' . $symbol;
                        }

                        return $indicators;
                    }),

                TernaryFilter::make('wholesale_enabled')
                    ->label(__('product.wholesale_enabled')),

                TernaryFilter::make('wholesale_is_price_negotiable')
                    ->label(__('product.wholesale_is_price_negotiable')),

                SelectFilter::make('uom_id')
                    ->label(__('unit_of_measure.unit_of_measure'))
                    ->relationship(
                        'unitOfMeasure',
                        'name_' . app()->getLocale(),
                        fn(Builder $query) => $query->filterByCompany($user->company_id)
                    )
                    ->searchable()
                    ->preload(),

                Filter::make('barcode')
                    ->schema([
                        TextInput::make('barcode')
                            ->label(__('product.barcode')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['barcode'])) {
                            return $query;
                        }

                        return $query->whereHas('barcodes', function (Builder $query) use ($data) {
                            $query->where('barcode', $data['barcode']);
                        });
                    }),

                Filter::make('attributes')
                    ->label(__('attribute.attributes'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('attribute_id')
                            ->label(__('attribute.attribute'))
                            ->options(fn() => Attribute::query()->filterByCompany($user->company_id)
                                ->pluck('name_' . app()->getLocale(), 'id'))
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('attribute_value_id', null)),
                        Select::make('attribute_value_id')
                            ->label(__('attribute.value'))
                            ->options(function (Get $get) {
                                if (! $get('attribute_id')) {
                                    return [];
                                }

                                return AttributeValue::where('attribute_id', $get('attribute_id'))
                                    ->pluck('value_' . app()->getLocale(), 'id');
                            })
                            ->multiple()
                            ->preload()
                            ->disabled(fn(Get $get) => ! $get('attribute_id')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['attribute_value_id'])) {
                            return $query;
                        }

                        return $query->whereHas('attributeValues', function (Builder $query) use ($data) {
                            $query->whereIn('attribute_values.id', (array) $data['attribute_value_id']);
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        if (empty($data['attribute_value_id'])) {
                            return [];
                        }

                        $attribute = Attribute::find($data['attribute_id']);
                        $values = AttributeValue::whereIn('id', (array) $data['attribute_value_id'])
                            ->pluck('value_' . app()->getLocale())
                            ->implode(', ');

                        return [
                            ($attribute?->{'name_' . app()->getLocale()} ?? __('attribute.attribute')) . ': ' . $values,
                        ];
                    }),

                Filter::make('low_stock')
                    ->label(__('product.low_stock'))
                    ->schema([
                        Toggle::make('low_stock')
                            ->label(__('product.low_stock'))
                            ->default(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['low_stock'] ?? false)) {
                            return $query;
                        }

                        return $query->whereNotNull('low_stock_threshold')
                            ->whereColumn('quantity', '<=', 'low_stock_threshold');
                    })
                    ->indicateUsing(function (array $data): array {
                        if (! ($data['low_stock'] ?? false)) {
                            return [];
                        }

                        return [__('product.low_stock_threshold')];
                    }),

                Filter::make('out_of_stock')
                    ->label(__('product.out_of_stock'))
                    ->schema([
                        Toggle::make('out_of_stock')
                            ->label(__('product.out_of_stock'))
                            ->default(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['out_of_stock'] ?? false)) {
                            return $query;
                        }

                        return $query->where('quantity', '<=', 0);
                    })
                    ->indicateUsing(function (array $data): array {
                        if (! ($data['out_of_stock'] ?? false)) {
                            return [];
                        }

                        return [__('product.out_of_stock')];
                    }),

                Filter::make('no_barcode')
                    ->label(__('product.no_barcode'))
                    ->schema([
                        Toggle::make('no_barcode')
                            ->label(__('product.no_barcode'))
                            ->default(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['no_barcode'] ?? false)) {
                            return $query;
                        }

                        return $query->whereDoesntHave('barcodes');
                    })
                    ->indicateUsing(function (array $data): array {
                        if (! ($data['no_barcode'] ?? false)) {
                            return [];
                        }

                        return [__('product.no_barcode')];
                    }),

            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::SevenExtraLarge)
                    ->label(__('product.add_variant'))
                    ->mutateDataUsing(function (array $data): array {
                        $this->tempVariantAttributes = $data['variant_attributes'] ?? [];
                        unset($data['variant_attributes']);

                        return $data;
                    })
                    ->after(function (Model $record) {
                        $valueIds = collect($this->tempVariantAttributes)->pluck('attribute_value_id')->filter();
                        $record->attributeValues()->sync($valueIds);
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::SevenExtraLarge)
                        ->mutateRecordDataUsing(function (array $data, Model $record): array {
                            $data['variant_attributes'] = $record->attributeValues->map(function ($value) {
                                return [
                                    'attribute_id' => $value->attribute_id,
                                    'attribute_value_id' => $value->id,
                                ];
                            })->toArray();

                            return $data;
                        })
                        ->mutateDataUsing(function (array $data): array {
                            $this->tempVariantAttributes = $data['variant_attributes'] ?? [];
                            unset($data['variant_attributes']);

                            return $data;
                        })
                        ->after(function (Model $record) {
                            $valueIds = collect($this->tempVariantAttributes)->pluck('attribute_value_id')->filter();

                            $record->attributeValues()->sync($valueIds);
                        }),
                    DeleteAction::make(),
                ]),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
