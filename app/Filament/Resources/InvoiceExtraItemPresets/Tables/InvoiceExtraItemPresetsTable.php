<?php

namespace App\Filament\Resources\InvoiceExtraItemPresets\Tables;

use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InvoiceExtraItemPresetsTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user()->load('company');

        return $table
            ->columns([
                TextColumn::make('store.name')
                    ->label(__('app.store'))
                    ->searchable()
                    ->sortable()
                    ->hidden($user->isStoreLevel()),
                TextColumn::make('name')
                    ->label(__('app.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('action_type')
                    ->label(__('extra_item.action_type'))
                    ->badge(),
                TextColumn::make('amount')
                    ->label(__('app.amount'))
                    ->numeric()
                    ->suffix(' '.$user->company->currency_symbol ?? 'ج.م')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('invoice_type')
                    ->label(__('extra_item.invoice_type'))
                    ->badge(),
                IconColumn::make('is_refundable')
                    ->label(__('extra_item.is_refundable'))
                    ->boolean(),
                ToggleColumn::make('is_active')
                    ->label(__('app.is_active')),
                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('app.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActionsColumnLabel(__('app.actions'))
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
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
