<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('customer.name'))
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('customer.phone'))
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('customer.email'))
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->searchable(),
                TextColumn::make('tax_number')
                    ->label(__('customer.tax_number'))
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('customer.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('customer.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('customer.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActionsColumnLabel(__('app.actions'))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->label(__('app.actions'))
                    ->tooltip(__('app.actions')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
