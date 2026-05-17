<?php

namespace App\Filament\Resources\PurchaseReturns\Tables;

use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use App\Models\User;
use App\Services\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnsTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user()->load('company');
        $currencySymbol = $user->company->currency_symbol ?? 'ج.م';

        return $table
            ->columns([
                TextColumn::make('return_number')
                    ->label(__('purchase_return.return_number'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->weight('bold')
                    ->sortable(),

                // TextColumn::make('vendor_credit_ref')
                //     ->label(__('purchase_return.vendor_credit_ref'))
                //     ->searchable()
                //     ->copyable()
                //     ->tooltip(__('app.click_to_copy_item'))
                //     ->toggleable(isToggledHiddenByDefault: true)
                //     ->placeholder('—'),

                TextColumn::make('originalInvoice.invoice_number')
                    ->label(__('purchase_return.original_invoice'))
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item')),

                TextColumn::make('vendor.name')
                    ->label(__('purchase_return.vendor'))
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('store.name_'.app()->getLocale())
                    ->label(__('purchase_return.store'))
                    ->visible(fn (): bool => $user->isCompanyLevel())
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('purchase_return.status'))
                    ->badge()
                    ->formatStateUsing(fn (PurchaseReturnStatus $state): string => $state->getLabel())
                    ->color(fn (PurchaseReturnStatus $state): string => $state->getColor()),

                TextColumn::make('total_amount')
                    ->label(__('purchase_return.total_amount'))
                    ->color(Color::Orange)
                    ->numeric(decimalPlaces: 2, locale: 'en')
                    ->prefix($currencySymbol.' ')
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->sortable(),

                TextColumn::make('return_reason')
                    ->label(__('purchase_return.return_reason'))
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('notes')
                    ->label(__('purchase_return.notes'))
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('returned_at')
                    ->label(__('purchase_return.returned_at'))
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label(__('purchase_return.created_by')),

                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->description(fn (PurchaseReturn $record) => $record->created_at->format('h:i ').($record->created_at->format('a') === 'am' ? 'ص' : 'م'))
                    ->dateTime('d-m-Y')
                    ->sortable(),

                TextColumn::make('finalizedBy.name')
                    ->label(__('purchase_return.finalized_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('finalized_at')
                    ->label(__('purchase_return.finalized_at'))
                    ->dateTime('d-m-Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('purchase_return.status'))
                    ->options([
                        PurchaseReturnStatus::Draft->value => PurchaseReturnStatus::Draft->getLabel(),
                        PurchaseReturnStatus::Finalized->value => PurchaseReturnStatus::Finalized->getLabel(),
                    ]),

                SelectFilter::make('original_invoice_id')
                    ->label(__('purchase_return.original_invoice'))
                    ->relationship('originalInvoice', 'invoice_number', fn(Builder $query) => $query->limit(50))
                    ->searchable()
                    ->default(request()->query('invoice_id')),

                SelectFilter::make('store_id')
                    ->label(__('purchase_return.store'))
                    ->relationship('store', 'name_' . app()->getLocale())
                    ->visible(fn (): bool => $user->isCompanyLevel()),

                SelectFilter::make('vendor_id')
                    ->label(__('purchase_return.vendor'))
                    ->relationship('vendor', 'name')
                    ->searchable(),

                SelectFilter::make('created_by')
                    ->label(__('purchase_return.created_by'))
                    ->relationship('createdBy', 'name')
                    ->searchable(),

                SelectFilter::make('finalized_by')
                    ->label(__('purchase_return.finalized_by'))
                    ->relationship('finalizedBy', 'name')
                    ->searchable(),

                Filter::make('returned_at')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('returned_from')->label(__('purchase_return.returned_at') . ' (' . __('app.from') . ')')->native(false)->locale(app()->getLocale()),
                                DatePicker::make('returned_until')->label(__('purchase_return.returned_at') . ' (' . __('app.until') . ')')->native(false)->locale(app()->getLocale()),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['returned_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('returned_at', '>=', $date),
                            )
                            ->when(
                                $data['returned_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('returned_at', '<=', $date),
                            );
                    }),

                Filter::make('created_at')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('created_from')->label(__('app.created_at') . ' (' . __('app.from') . ')')->native(false)->locale(app()->getLocale()),
                                DatePicker::make('created_until')->label(__('app.created_at') . ' (' . __('app.until') . ')')->native(false)->locale(app()->getLocale()),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                Filter::make('finalized_at')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('finalized_from')->label(__('purchase_return.finalized_at') . ' (' . __('app.from') . ')')->native(false)->locale(app()->getLocale()),
                                DatePicker::make('finalized_until')->label(__('purchase_return.finalized_at') . ' (' . __('app.until') . ')')->native(false)->locale(app()->getLocale()),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['finalized_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('finalized_at', '>=', $date),
                            )
                            ->when(
                                $data['finalized_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('finalized_at', '<=', $date),
                            );
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->recordActionsColumnLabel(__('app.actions'))
            ->recordActions([
                ActionGroup::make([
                    Action::make('finalize')
                        ->label(__('purchase_return.finalize'))
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(__('purchase_return.finalize_confirm_title'))
                        ->modalDescription(__('purchase_return.finalize_confirm_body'))
                        ->authorize('finalize_purchase_return')
                        ->visible(fn (PurchaseReturn $record): bool => ! $record->isFinalized())
                        ->action(function (PurchaseReturn $record) {
                            try {
                                PurchaseInvoiceService::make()->finalizeReturn($record);
                                Notification::make()
                                    ->title(__('purchase_return.finalize_success'))
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title(__('purchase_return.finalize_failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    ViewAction::make()
                        ->authorize('view_purchase_return'),
                    EditAction::make()
                        ->authorize('update_purchase_return')
                        ->visible(fn (PurchaseReturn $record): bool => ! $record->isFinalized()),
                    DeleteAction::make()
                        ->authorize('delete_purchase_return')
                        ->visible(fn (PurchaseReturn $record): bool => ! $record->isFinalized()),
                ])
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }
}
