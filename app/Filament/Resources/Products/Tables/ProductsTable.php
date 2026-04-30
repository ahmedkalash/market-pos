<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->columns([
                TextColumn::make('name_'.app()->getLocale())
                    ->label(__('app.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('store.name_'.app()->getLocale())
                    ->label(__('app.store'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => $user->isCompanyLevel()),

                TextColumn::make('category.name_'.app()->getLocale())
                    ->label(__('product_category.category'))
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('brand.name_'.app()->getLocale())
                    ->label(__('brand.brand'))
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('taxClass.name_'.app()->getLocale())
                    ->label(__('tax_class.tax_class'))
                    ->badge()
                    ->color('warning'),

                TextColumn::make('variants_count')
                    ->label(__('product.variants'))
                    ->counts('variants')
                    ->badge()
                    ->color('info'),

                ToggleColumn::make('is_active')
                    ->label(__('app.active')),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label(__('app.store'))
                    ->relationship('store', 'name_'.app()->getLocale(),
                        fn (Builder $query) => $query->filterByCompany($user->company_id))
                    ->visible(fn () => $user->isCompanyLevel()),

                TernaryFilter::make('is_active')
                    ->label(__('app.active')),

                SelectFilter::make('category_id')
                    ->label(__('product_category.category'))
                    ->relationship('category', 'name_'.app()->getLocale())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('brand_id')
                    ->label(__('brand.brand'))
                    ->relationship('brand', 'name_'.app()->getLocale())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('tax_class_id')
                    ->label(__('tax_class.tax_class'))
                    ->relationship('taxClass', 'name_'.app()->getLocale())
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('retail_is_price_negotiable')
                    ->label(__('product.negotiable'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        $isNegotiable = $data['value'] == '1';

                        return $query->whereHas('variants', function (Builder $query) use ($isNegotiable) {
                            $query->where('retail_is_price_negotiable', $isNegotiable);
                        });
                    }),

                Filter::make('retail_price_range')
                    ->label(__('product.retail_price_range'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('retail_price_from')
                            ->label(__('product.retail_price_from'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn () => $user->company->currency_symbol),
                        TextInput::make('retail_price_to')
                            ->label(__('product.retail_price_to'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn () => $user->company->currency_symbol),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['retail_price_from']),
                                fn (Builder $q) => $q->whereHas('variants', fn (Builder $v) => $v->where('retail_price', '>=', $data['retail_price_from']))
                            )
                            ->when(
                                filled($data['retail_price_to']),
                                fn (Builder $q) => $q->whereHas('variants', fn (Builder $v) => $v->where('retail_price', '<=', $data['retail_price_to']))
                            );
                    })
                    ->indicateUsing(function (array $data) use ($user): array {
                        $indicators = [];
                        $symbol = $user->company->currency_symbol;

                        if (filled($data['retail_price_from'])) {
                            $indicators[] = __('product.retail_price_from').': '.$data['retail_price_from'].' '.$symbol;
                        }

                        if (filled($data['retail_price_to'])) {
                            $indicators[] = __('product.retail_price_to').': '.$data['retail_price_to'].' '.$symbol;
                        }

                        return $indicators;
                    }),

                TernaryFilter::make('wholesale_enabled')
                    ->label(__('product.wholesale_enabled'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        $isEnabled = $data['value'] == '1';

                        return $query->whereHas('variants', function (Builder $query) use ($isEnabled) {
                            $query->where('wholesale_enabled', $isEnabled);
                        });
                    }),

                TernaryFilter::make('wholesale_is_price_negotiable')
                    ->label(__('product.wholesale_is_price_negotiable'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        $isNegotiable = $data['value'] == '1';

                        return $query->whereHas('variants', function (Builder $query) use ($isNegotiable) {
                            $query->where('wholesale_is_price_negotiable', $isNegotiable);
                        });
                    }),

                Filter::make('wholesale_price_range')
                    ->label(__('product.wholesale_price_range'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('wholesale_price_from')
                            ->label(__('product.wholesale_price_from'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn () => $user->company->currency_symbol),
                        TextInput::make('wholesale_price_to')
                            ->label(__('product.wholesale_price_to'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn () => $user->company->currency_symbol),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['wholesale_price_from']),
                                fn (Builder $q) => $q->whereHas('variants', fn (Builder $v) => $v->where('wholesale_price', '>=', $data['wholesale_price_from']))
                            )
                            ->when(
                                filled($data['wholesale_price_to']),
                                fn (Builder $q) => $q->whereHas('variants', fn (Builder $v) => $v->where('wholesale_price', '<=', $data['wholesale_price_to']))
                            );
                    })
                    ->indicateUsing(function (array $data) use ($user): array {
                        $indicators = [];
                        $symbol = $user->company->currency_symbol;

                        if (filled($data['wholesale_price_from'])) {
                            $indicators[] = __('product.wholesale_price_from').': '.$data['wholesale_price_from'].' '.$symbol;
                        }

                        if (filled($data['wholesale_price_to'])) {
                            $indicators[] = __('product.wholesale_price_to').': '.$data['wholesale_price_to'].' '.$symbol;
                        }

                        return $indicators;
                    }),

                SelectFilter::make('uom_id')
                    ->label(__('unit_of_measure.unit_of_measure'))
                    ->options(fn () => UnitOfMeasure::query()->filterByCompany($user->company_id)
                        ->pluck('name_'.app()->getLocale(), 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('variants', function (Builder $query) use ($data) {
                            $query->where('uom_id', $data['value']);
                        });
                    })
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

                        return $query->whereHas('variants.barcodes', function (Builder $query) use ($data) {
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
                            ->options(fn () => Attribute::query()->filterByCompany($user->company_id)
                                ->pluck('name_'.app()->getLocale(), 'id'))
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('attribute_value_id', null)),
                        Select::make('attribute_value_id')
                            ->label(__('attribute.value'))
                            ->options(function (Get $get) {
                                if (! $get('attribute_id')) {
                                    return [];
                                }

                                return AttributeValue::where('attribute_id', $get('attribute_id'))
                                    ->pluck('value_'.app()->getLocale(), 'id');
                            })
                            ->multiple()
                            ->preload()
                            ->disabled(fn (Get $get) => ! $get('attribute_id')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['attribute_value_id'])) {
                            return $query;
                        }

                        return $query->whereHas('variants.attributeValues', function (Builder $query) use ($data) {
                            $query->whereIn('attribute_values.id', (array) $data['attribute_value_id']);
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        if (empty($data['attribute_value_id'])) {
                            return [];
                        }

                        $attribute = Attribute::find($data['attribute_id']);
                        $values = AttributeValue::whereIn('id', (array) $data['attribute_value_id'])
                            ->pluck('value_'.app()->getLocale())
                            ->implode(', ');

                        return [
                            ($attribute?->{'name_'.app()->getLocale()} ?? __('attribute.attribute')).': '.$values,
                        ];
                    }),

                Filter::make('low_stock')
                    ->label(__('product.low_stock'))
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('low_stock')
                            ->label(__('product.low_stock'))
                            ->default(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['low_stock'] ?? false)) {
                            return $query;
                        }

                        return $query->whereHas('variants', function (Builder $query) {
                            $query->whereNotNull('low_stock_threshold')
                                ->whereColumn('quantity', '<=', 'low_stock_threshold');
                        });
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

                        return $query->whereHas('variants', function (Builder $query) {
                            $query->where('quantity', '<=', 0);
                        });
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

                        return $query->whereHas('variants', function (Builder $query) {
                            $query->whereDoesntHave('barcodes');
                        });
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
            ->recordActionsColumnLabel(__('app.actions'))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
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
