<?php

namespace App\Filament\Resources\UnitOfMeasures\Tables;

use App\Models\User;
use Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitOfMeasuresTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->recordActionsColumnLabel(__('app.actions'))
            ->columns([
                TextColumn::make('store.name_'.app()->getLocale())
                    ->label(__('app.store'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => $user->isCompanyLevel()),
                TextColumn::make('name_ar')
                    ->label(__('unit_of_measure.name_ar'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_en')
                    ->label(__('unit_of_measure.name_en'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abbreviation_ar')
                    ->label(__('unit_of_measure.abbreviation_ar'))
                    ->searchable(),
                TextColumn::make('abbreviation_en')
                    ->label(__('unit_of_measure.abbreviation_en'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label(__('app.store'))
                    ->relationship('store', 'name_'.app()->getLocale(),
                        fn (Builder $query) => $query->filterByCompany($user->company_id))
                    ->visible(fn () => $user->isCompanyLevel()),
            ])
            ->recordActions([
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
