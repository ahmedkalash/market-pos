<?php

namespace App\Filament\Resources\SaleInvoices\Tables;

use App\Enums\PaymentMethod;
use App\Enums\SaleInvoiceReturnStatus;
use App\Enums\SaleInvoiceStatus;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SaleInvoicesTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user()->load('company');
        $currencySymbol = $user->company->currency_symbol ?? 'ج.م';

        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('sale_invoice.invoice_number'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('store.name_'.app()->getLocale())
                    ->label(__('sale_invoice.store'))
                    ->visible(fn (): bool => $user->isCompanyLevel())
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('customer.name')
                    ->label(__('customer.model_label'))
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('sale_invoice.status'))
                    ->badge()
                    ->formatStateUsing(fn (SaleInvoiceStatus $state): string => $state->getLabel())
                    ->color(fn (SaleInvoiceStatus $state): string => $state->getColor()),

                TextColumn::make('return_status')
                    ->label(__('sale_invoice.return_status'))
                    ->badge()
                    ->formatStateUsing(fn (SaleInvoiceReturnStatus $state): string => $state->getLabel())
                    ->color(fn (SaleInvoiceReturnStatus $state): string => $state->getColor())
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label(__('sale_invoice.payment_method'))
                    ->badge()
                    ->formatStateUsing(fn (?PaymentMethod $state): string => $state?->getLabel() ?? '—')
                    ->color(fn (?PaymentMethod $state): string => $state?->getColor() ?? 'gray')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label(__('sale_invoice.total_amount'))
                    ->color(Color::Blue)
                    ->numeric(decimalPlaces: 2, locale: 'en')
                    ->prefix($currencySymbol.' ')
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label(__('sale_invoice.created_by'))
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item')),

                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->description(fn (SaleInvoice $record) => $record->created_at->format('h:i ').($record->created_at->format('a') === 'am' ? 'ص' : 'م'))
                    ->dateTime('d-m-Y')
                    ->sortable(),

                TextColumn::make('finalizedBy.name')
                    ->label(__('sale_invoice.finalized_by'))
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('finalized_at')
                    ->label(__('sale_invoice.finalized_at'))
                    ->description(fn (SaleInvoice $record): ?string => $record->finalized_at ? $record->finalized_at->format('h:i ').($record->finalized_at->format('a') === 'am' ? 'ص' : 'م') : null)
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('app.updated_at'))
                    ->description(fn (SaleInvoice $record): ?string => $record->updated_at ? $record->updated_at->format('h:i ').($record->updated_at->format('a') === 'am' ? 'ص' : 'م') : null)
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('invoice_number')
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label(__('sale_invoice.invoice_number')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['invoice_number'],
                            fn (Builder $query, $number): Builder => $query->where('invoice_number', 'like', "{$number}%"),
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['invoice_number'] ?? null)) {
                            return null;
                        }

                        return __('sale_invoice.invoice_number').': '.$data['invoice_number'];
                    }),

                SelectFilter::make('created_by')
                    ->label(__('sale_invoice.created_by'))
                    ->relationship('createdBy', 'name')
                    ->multiple()
                    ->searchable(),

                SelectFilter::make('finalized_by')
                    ->label(__('sale_invoice.finalized_by'))
                    ->relationship('finalizedBy', 'name')
                    ->multiple()
                    ->searchable(),

                Filter::make('product_barcode')
                    ->schema([
                        TextInput::make('barcode')
                            ->label(__('sale_invoice.product_barcode')),
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
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['barcode'] ?? null)) {
                            return null;
                        }

                        return __('sale_invoice.product_barcode').': '.$data['barcode'];
                    }),

                TernaryFilter::make('has_notes')
                    ->label(__('sale_invoice.has_notes'))
                    ->placeholder(__('app.all'))
                    ->trueLabel(__('sale_invoice.with_notes'))
                    ->falseLabel(__('sale_invoice.without_notes'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('notes')->where('notes', '!=', ''),
                        false: fn (Builder $query) => $query->whereNull('notes')->orWhere('notes', ''),
                        blank: fn (Builder $query) => $query,
                    ),

                SelectFilter::make('status')
                    ->label(__('sale_invoice.status'))
                    ->multiple()
                    ->options([
                        SaleInvoiceStatus::Draft->value => __('sale_invoice.status_draft'),
                        SaleInvoiceStatus::Finalized->value => __('sale_invoice.status_finalized'),
                    ]),

                SelectFilter::make('return_status')
                    ->label(__('sale_invoice.return_status'))
                    ->multiple()
                    ->options([
                        SaleInvoiceReturnStatus::None->value => __('sale_invoice.return_status_none'),
                        SaleInvoiceReturnStatus::PartiallyReturned->value => __('sale_invoice.return_status_partially_returned'),
                        SaleInvoiceReturnStatus::FullyReturned->value => __('sale_invoice.return_status_fully_returned'),
                    ]),

                SelectFilter::make('payment_method')
                    ->label(__('sale_invoice.payment_method'))
                    ->multiple()
                    ->options([
                        PaymentMethod::Cash->value => __('sale_invoice.payment_method_cash'),
                        PaymentMethod::Card->value => __('sale_invoice.payment_method_card'),
                        PaymentMethod::Split->value => __('sale_invoice.payment_method_split'),
                    ]),

                SelectFilter::make('store_id')
                    ->label(__('sale_invoice.store'))
                    ->relationship('store', 'name_'.app()->getLocale())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => $user->isCompanyLevel()),

                SelectFilter::make('customer_id')
                    ->label(__('customer.model_label'))
                    ->relationship('customer', 'name')
                    ->searchable(),

                Filter::make('total_amount')
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
                            ->when(
                                $data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '>=', $amount),
                            )
                            ->when(
                                $data['max_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '<=', $amount),
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
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('sale_invoice.created_from')),
                                DatePicker::make('created_until')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('sale_invoice.created_until')),
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
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.created_from').': '.Carbon::parse($data['created_from'])->toFormattedDateString())
                                ->removeField('created_from');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.created_until').': '.Carbon::parse($data['created_until'])->toFormattedDateString())
                                ->removeField('created_until');
                        }

                        return $indicators;
                    }),

                Filter::make('finalized_at')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('finalized_from')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('sale_invoice.finalized_from')),
                                DatePicker::make('finalized_until')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('sale_invoice.finalized_until')),
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
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['finalized_from'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.finalized_from').': '.Carbon::parse($data['finalized_from'])->toFormattedDateString())
                                ->removeField('finalized_from');
                        }
                        if ($data['finalized_until'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.finalized_until').': '.Carbon::parse($data['finalized_until'])->toFormattedDateString())
                                ->removeField('finalized_until');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->recordActionsColumnLabel(__('app.actions'))
            ->recordActions([
                ActionGroup::make([
                    Action::make('finalize')
                        ->label(__('sale_invoice.finalize'))
                        ->modalHeading(__('sale_invoice.finalize'))
                        ->modalDescription(__('sale_invoice.finalize_confirmation'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->authorize('finalize_sale_invoice')
                        ->visible(fn (SaleInvoice $record): bool => ! $record->isFinalized())
                        ->schema([
                            Select::make('payment_method')
                                ->label(__('sale_invoice.payment_method'))
                                ->options([
                                    PaymentMethod::Cash->value => __('sale_invoice.payment_method_cash'),
                                    PaymentMethod::Card->value => __('sale_invoice.payment_method_card'),
                                    PaymentMethod::Split->value => __('sale_invoice.payment_method_split'),
                                ])
                                ->required(),
                        ])
                        ->action(function (SaleInvoice $record, array $data) {
                            try {
                                $paymentMethod = PaymentMethod::from($data['payment_method']);
                                SaleInvoiceService::make()->finalize($record, $paymentMethod);

                                Notification::make()
                                    ->title(__('sale_invoice.finalized_success'))
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title(__('sale_invoice.finalize_failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    ViewAction::make()
                        ->authorize('view_sale_invoice'),
                    EditAction::make()
                        ->authorize('update_sale_invoice')
                        ->visible(fn (SaleInvoice $record): bool => ! $record->isFinalized()),
                    DeleteAction::make()
                        ->authorize('delete_sale_invoice')
                        ->visible(fn (SaleInvoice $record): bool => ! $record->isFinalized()),
                ]),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
