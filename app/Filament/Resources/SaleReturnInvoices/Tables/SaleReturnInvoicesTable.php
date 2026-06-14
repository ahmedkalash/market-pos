<?php

namespace App\Filament\Resources\SaleReturnInvoices\Tables;

use App\Enums\SaleReturnStatus;
use App\Models\SaleReturnInvoice;
use App\Models\User;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SaleReturnInvoicesTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->columns([
                TextColumn::make('return_number')
                    ->label(__('app.return_number'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->sortable(),
                TextColumn::make('originalInvoice.invoice_number')
                    ->label(__('app.original_invoice_id'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('app.customer'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('app.status'))
                    ->badge(),
                TextColumn::make('total_refund_amount')
                    ->label(__('app.total_refund_amount'))
                    ->numeric()
                    ->badge()
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('items_refund_total')
                    ->label(__('app.items_refund_total'))
                    ->numeric()
                    ->badge()
                    ->color('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_items_total')
                    ->label(__('app.extra_items_total'))
                    ->numeric()
                    ->badge()
                    ->color(fn ($record) => $record->extra_items_total < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('returned_at')
                    ->label(__('app.returned_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('finalized_at')
                    ->label(__('app.finalized_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('finalizedBy.name')
                    ->label(__('app.finalized_by'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')
                    ->label(__('app.created_by'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('app.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filters([
                SelectFilter::make('store_id')
                    ->label(__('sale_invoice.store'))
                    ->relationship('store', lang_suffix('name'))
                    ->multiple()
                    ->searchable(['name_en', 'name_ar'])
                    ->preload()
                    ->visible(fn (): bool => $user->isCompanyLevel()),

                Filter::make('return_number')
                    ->schema([
                        TextInput::make('return_number')
                            ->label(__('app.return_number')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['return_number'],
                            fn (Builder $query, $number): Builder => $query->where('return_number', 'like', "{$number}%"),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['return_number'] ?? null) {
                            $indicators[] = Indicator::make(__('app.return_number').': '.$data['return_number'])
                                ->removeField('return_number');
                        }

                        return $indicators;
                    }),

                Filter::make('original_invoice_number')
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label(__('app.original_invoice_id')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['invoice_number'],
                            fn (Builder $query, $number): Builder => $query->whereHas('originalInvoice', fn ($q) => $q->where('invoice_number', 'like', "{$number}%")),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['invoice_number'] ?? null) {
                            $indicators[] = Indicator::make(__('app.original_invoice_id').': '.$data['invoice_number'])
                                ->removeField('invoice_number');
                        }

                        return $indicators;
                    }),

                SelectFilter::make('customer_id')
                    ->label(__('customer.model_label'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('product_barcode')
                    ->schema([
                        TextInput::make('barcode')
                            ->label(__('sale_invoice.product_barcode')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['barcode'] ?? null,
                            fn (Builder $query, $barcode): Builder => $query
                                ->whereHas('items.variant.barcodes', fn (Builder $barcodeQuery) => $barcodeQuery->where('barcode', $barcode))
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['barcode'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.product_barcode').': '.$data['barcode'])
                                ->removeField('barcode');
                        }

                        return $indicators;
                    }),

                Filter::make('product_name')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('product.model_label')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['name'] ?? null,
                            fn (Builder $query, $name): Builder => $query->whereHas(
                                'items.variant', fn (Builder $variantQuery) => $variantQuery->where(fn ($q) => $q
                                    ->whereNameLike($name)
                                    ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->whereNameLike($name))
                                )
                            )
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['name'] ?? null) {
                            $indicators[] = Indicator::make(__('product.model_label').': '.$data['name'])
                                ->removeField('name');
                        }

                        return $indicators;
                    }),

                SelectFilter::make('status')
                    ->label(__('app.status'))
                    ->multiple()
                    ->options(SaleReturnStatus::class),

                SelectFilter::make('created_by')
                    ->label(__('app.created_by'))
                    ->relationship('createdBy', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                SelectFilter::make('finalized_by')
                    ->label(__('app.finalized_by'))
                    ->relationship('finalizedBy', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Filter::make('customer_phone')
                    ->schema([
                        TextInput::make('phone')
                            ->label(__('customer.phone'))
                            ->tel(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['phone'] ?? null,
                            fn (Builder $query, $phone): Builder => $query->whereHas(
                                'customer', fn (Builder $customerQuery) => $customerQuery->where('phone', 'like', "{$phone}%")
                            )
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['phone'] ?? null) {
                            $indicators[] = Indicator::make(__('customer.phone').': '.$data['phone'])
                                ->removeField('phone');
                        }

                        return $indicators;
                    }),

                Filter::make('total_refund_amount')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_amount')
                                    ->label(__('sale_invoice.amount_min'))
                                    ->numeric(),
                                TextInput::make('max_amount')
                                    ->label(__('sale_invoice.amount_max'))
                                    ->numeric(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_refund_amount', '>=', $amount),
                            )
                            ->when($data['max_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_refund_amount', '<=', $amount),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['min_amount'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.amount_min').': '.$data['min_amount'])
                                ->removeField('min_amount');
                        }
                        if ($data['max_amount'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.amount_max').': '.$data['max_amount'])
                                ->removeField('max_amount');
                        }

                        return $indicators;
                    }),

                Filter::make('created_at')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('created_from')
                                    ->label(__('sale_invoice.created_from'))
                                    ->format('Y-m-d')
                                    ->displayFormat('d/m/Y'),
                                DatePicker::make('created_until')
                                    ->label(__('sale_invoice.created_until'))
                                    ->format('Y-m-d')
                                    ->displayFormat('d/m/Y'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when($data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.created_from').': '.$data['created_from'])
                                ->removeField('created_from');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.created_until').': '.$data['created_until'])
                                ->removeField('created_until');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('finalize')
                        ->label(__('sale_invoice.finalize'))
                        ->modalHeading(__('sale_invoice.finalize'))
                        ->modalDescription(__('sale_return.finalize_confirmation'))
                        ->successNotificationTitle(__('sale_invoice.finalized_success'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->authorize('finalize_sale_return')
                        ->visible(fn (SaleReturnInvoice $record): bool => ! $record->isFinalized())
                        ->action(function (SaleReturnInvoice $record) {
                            try {
                                SaleInvoiceService::make()->finalizeReturn($record);
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title(__('sale_invoice.finalize_failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                throw (new Halt)->rollBackDatabaseTransaction(true);
                            }
                        }),
                    ViewAction::make()
                        ->authorize('view_sale_return'),
                    EditAction::make()
                        ->visible(fn ($record) => $record->status === SaleReturnStatus::Draft),
                    DeleteAction::make()
                        ->visible(fn ($record) => $record->status === SaleReturnStatus::Draft),
                ]),
            ])
            ->recordActionsColumnLabel(__('app.actions'))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
