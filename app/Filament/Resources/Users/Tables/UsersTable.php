<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('avatar')
                    ->label(__('app.avatar'))
                    ->collection('avatar')
                    ->circular(),

                TextColumn::make('name')
                    ->label(__('app.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('app.email'))
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label(__('app.role'))
                    ->badge(),

                TextColumn::make('store.name_en')
                    ->label(__('app.store'))
                    ->placeholder(__('app.all_stores')),

                TextColumn::make('phone')
                    ->label(__('app.phone'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label(__('app.active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('app.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
