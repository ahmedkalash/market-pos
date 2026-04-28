<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\UnitOfMeasure;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
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
                    ->visible(fn () => auth()->user()->isCompanyLevel()),

                TextColumn::make('category.name_'.app()->getLocale())
                    ->label(__('product_category.category'))
                    ->sortable()
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
                    ->relationship('store', 'name_'.app()->getLocale(), function (Builder $query) {
                        /** @var User $user */
                        $user = Auth::user();

                        return $query->where('company_id', $user->company_id);
                    })
                    ->visible(fn () => Auth::user()->isCompanyLevel()),

                TernaryFilter::make('is_active')
                    ->label(__('app.active')),

                SelectFilter::make('category_id')
                    ->label(__('product_category.category'))
                    ->relationship('category', 'name_'.app()->getLocale())
                    ->searchable()
                    ->preload(),

                SelectFilter::make('tax_class_id')
                    ->label(__('tax_class.tax_class'))
                    ->relationship('taxClass', 'name_'.app()->getLocale())
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('price_is_negotiable')
                    ->label(__('product.negotiable'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        $isNegotiable = $data['value'] == '1';

                        return $query->whereHas('variants', function (Builder $query) use ($isNegotiable) {
                            $query->where('price_is_negotiable', $isNegotiable);
                        });
                    }),

                SelectFilter::make('uom_id')
                    ->label(__('unit_of_measure.unit_of_measure'))
                    ->options(function () {
                        /** @var User $user */
                        $user = Auth::user();

                        return UnitOfMeasure::where('company_id', $user->company_id)
                            ->pluck('name_'.app()->getLocale(), 'id');
                    })
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
                            $query->where('barcode',  $data['barcode']);
                        });
                    }),

                Filter::make('low_stock')
                    ->label(__('product.low_stock_threshold'))
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('variants', function (Builder $query) {
                            $query->whereNotNull('low_stock_threshold')
                                ->whereColumn('quantity', '<=', 'low_stock_threshold');
                        });
                    })
                    ->toggle(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
