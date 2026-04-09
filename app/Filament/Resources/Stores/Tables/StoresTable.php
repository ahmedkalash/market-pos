<?php

namespace App\Filament\Resources\Stores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_en')
                    ->label(__('Name (English)'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_ar')
                    ->label(__('Name (Arabic)'))
                    ->searchable(),

                TextColumn::make('phone')
                    ->label(__('app.phone'))
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label(__('app.active'))
                    ->boolean(),

                TextColumn::make('users_count')
                    ->label(__('app.users'))
                    ->counts('users')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label(__('app.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
