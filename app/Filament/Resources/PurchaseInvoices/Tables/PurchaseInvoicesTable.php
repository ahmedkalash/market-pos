<?php

namespace App\Filament\Resources\PurchaseInvoices\Tables;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Services\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user()->load('company');
        $currencySymbol = $user->company->currency_symbol ?? 'ج.م';

        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('purchase_invoice.invoice_number'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('vendor_invoice_ref')
                    ->label(__('purchase_invoice.vendor_invoice_ref'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('vendor.name')
                    ->label(__('purchase_invoice.vendor'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->placeholder('—'),

                TextColumn::make('store.name_'.app()->getLocale())
                    ->label(__('purchase_invoice.store'))
                    ->visible(fn (): bool => $user->isCompanyLevel())
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('purchase_invoice.status'))
                    ->badge()
                    ->formatStateUsing(fn (PurchaseInvoiceStatus $state): string => $state->getLabel())
                    ->color(fn (PurchaseInvoiceStatus $state): string => $state->getColor()),

                TextColumn::make('total_amount')
                    ->label(__('purchase_invoice.total_amount'))
                    ->color(Color::Blue)
                    ->numeric(decimalPlaces: 2, locale: 'en')
                    ->prefix($currencySymbol.' ')
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->sortable(),

                TextColumn::make('received_at')
                    ->label(__('purchase_invoice.received_at'))
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label(__('purchase_invoice.created_by')),

                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->description(fn (PurchaseInvoice $record) => $record->created_at->format('h:i ').($record->created_at->format('a') === 'am' ? 'ص' : 'م'))
                    ->dateTime('d-m-Y')
                    ->sortable(),

                TextColumn::make('finalizedBy.name')
                    ->label(__('purchase_invoice.finalized_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('finalized_at')
                    ->label(__('purchase_invoice.finalized_at'))
                    ->description(fn (PurchaseInvoice $record): ?string => $record->finalized_at ? $record->finalized_at->format('h:i ').($record->finalized_at->format('a') === 'am' ? 'ص' : 'م') : null)
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('app.updated_at'))
                    ->description(fn (PurchaseInvoice $record): ?string => $record->updated_at ? $record->updated_at->format('h:i ').($record->updated_at->format('a') === 'am' ? 'ص' : 'م') : null)
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('invoice_number')
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label(__('purchase_invoice.invoice_number')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['invoice_number'],
                            fn (Builder $query, $number): Builder => $query->where('invoice_number', 'like', "{$number}%"),
                        );
                    }),

                Filter::make('vendor_invoice_ref')
                    ->schema([
                        TextInput::make('vendor_invoice_ref')
                            ->label(__('purchase_invoice.vendor_invoice_ref')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['vendor_invoice_ref'],
                            fn (Builder $query, $ref): Builder => $query->where('vendor_invoice_ref', 'like', "{$ref}%"),
                        );
                    }),

                SelectFilter::make('created_by')
                    ->label(__('purchase_invoice.created_by'))
                    ->relationship('createdBy', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                SelectFilter::make('finalized_by')
                    ->label(__('purchase_invoice.finalized_by'))
                    ->relationship('finalizedBy', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Filter::make('product_barcode')
                    ->schema([
                        TextInput::make('barcode')
                            ->label(__('purchase_invoice.product_barcode')),
                    ])
                    ->query(function (Builder $query, array $data) use ($user): Builder {
                        return $query->when(
                            $data['barcode'] ?? null,
                            fn (Builder $query, $barcode): Builder => $query->whereHas(
                                'items',
                                fn (Builder $query) => $query->whereIn('product_variant_id', function ($subQuery) use ($barcode, $user) {
                                    $subQuery->select('product_barcodes.product_variant_id')
                                        ->from('product_barcodes')
                                        ->join('product_variants', 'product_variants.id', '=', 'product_barcodes.product_variant_id')
                                        ->join('products', 'products.id', '=', 'product_variants.product_id')
                                        ->whereIn('products.store_id', function ($storeSubQuery) use ($user) {
                                            $storeSubQuery->select('id')
                                                ->from('stores')
                                                ->where('company_id', $user->company_id);
                                        })
                                        ->where('product_barcodes.barcode', $barcode);
                                })
                            ),
                        );
                    }),

                TernaryFilter::make('has_notes')
                    ->label(__('purchase_invoice.has_notes'))
                    ->placeholder(__('app.all'))
                    ->trueLabel(__('purchase_invoice.with_notes'))
                    ->falseLabel(__('purchase_invoice.without_notes'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('notes')->where('notes', '!=', ''),
                        false: fn (Builder $query) => $query->whereNull('notes')->orWhere('notes', ''),
                        blank: fn (Builder $query) => $query,
                    ),

                // TAX FEATURE POSTPONED
                // TernaryFilter::make('has_tax')
                //     ->label(__('purchase_invoice.has_tax'))
                //     ->placeholder(__('app.all'))
                //     ->trueLabel(__('purchase_invoice.taxable'))
                //     ->falseLabel(__('purchase_invoice.tax_exempt'))
                //     ->queries(
                //         true: fn (Builder $query) => $query->where('total_tax_amount', '>', 0),
                //         false: fn (Builder $query) => $query->where('total_tax_amount', '<=', 0),
                //         blank: fn (Builder $query) => $query,
                //     ),

                SelectFilter::make('status')
                    ->label(__('purchase_invoice.status'))
                    ->multiple()
                    ->options([
                        PurchaseInvoiceStatus::Draft->value => __('purchase_invoice.status_draft'),
                        PurchaseInvoiceStatus::Finalized->value => __('purchase_invoice.status_finalized'),
                    ]),

                SelectFilter::make('vendor_id')
                    ->label(__('purchase_invoice.vendor'))
                    ->relationship('vendor', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                SelectFilter::make('store_id')
                    ->label(__('purchase_invoice.store'))
                    ->relationship('store', 'name_'.app()->getLocale())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => $user->isCompanyLevel()),

                Filter::make('total_amount')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_amount')
                                    ->label(__('purchase_invoice.amount_min'))
                                    ->numeric(),
                                TextInput::make('max_amount')
                                    ->label(__('purchase_invoice.amount_max'))
                                    ->numeric(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '>=', $amount),
                            )
                            ->when(
                                $data['max_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '<=', $amount),
                            );
                    }),

                Filter::make('received_at')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('received_from')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('purchase_invoice.received_from')),
                                DatePicker::make('received_until')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('purchase_invoice.received_until')),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['received_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('received_at', '>=', $date),
                            )
                            ->when(
                                $data['received_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('received_at', '<=', $date),
                            );
                    }),

                Filter::make('created_at')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('created_from')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('purchase_invoice.created_from')),
                                DatePicker::make('created_until')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('purchase_invoice.created_until')),
                            ]),
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
                                DatePicker::make('finalized_from')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('purchase_invoice.finalized_from')),
                                DatePicker::make('finalized_until')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('purchase_invoice.finalized_until')),
                            ]),
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
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->recordActionsColumnLabel(__('app.actions'))
            ->recordActions([
                ActionGroup::make([
                    Action::make('finalize')
                        ->label(__('purchase_invoice.finalize'))
                        ->modalHeading(__('purchase_invoice.finalize'))
                        ->modalDescription(__('purchase_invoice.finalize_confirmation'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->authorize('finalize_purchase_invoice')
                        ->visible(fn (PurchaseInvoice $record): bool => ! $record->isFinalized())
                        ->action(function (PurchaseInvoice $record) {
                            try {
                                PurchaseInvoiceService::make()->finalize($record);

                                Notification::make()
                                    ->title(__('purchase_invoice.finalized_success'))
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title(__('purchase_invoice.finalize_failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    ViewAction::make()
                        ->authorize('view_purchase_invoice'),
                    EditAction::make()
                        ->authorize('update_purchase_invoice')
                        ->visible(fn (PurchaseInvoice $record): bool => ! $record->isFinalized()),
                    DeleteAction::make()
                        ->authorize('delete_purchase_invoice')
                        ->visible(fn (PurchaseInvoice $record): bool => ! $record->isFinalized()),
                ]),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
