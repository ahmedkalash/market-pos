<?php

namespace App\Filament\Resources\ProductCategories\Tables;

use App\Models\User;
use Auth;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->recordActionsColumnLabel(__('app.actions'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label(__('app.image'))
                    ->collection('image')
                    ->conversion('thumb')
                    ->circular(),
                TextColumn::make('store.name_'.app()->getLocale())
                    ->label(__('app.store'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => $user->isCompanyLevel()),
                TextColumn::make('name_en')
                    ->label(__('product_category.name_en'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label(__('product_category.name_ar'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name_en')
                    ->label(__('product_category.parent_category'))
                    ->default(__('product_category.no_parent_category'))
                    ->color(fn ($record) => $record->parent_id ? 'primary' : 'danger')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('product_category.active')),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label(__('app.store'))
                    ->relationship('store', 'name_'.app()->getLocale(), fn (Builder $query) => $query->filterByCompany($user->company_id))
                    ->visible(fn () => $user->isCompanyLevel()),

                TernaryFilter::make('is_active')
                    ->label(__('product_category.active_status')),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
