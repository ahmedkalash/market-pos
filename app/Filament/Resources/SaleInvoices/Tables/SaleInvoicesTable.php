<?php

namespace App\Filament\Resources\SaleInvoices\Tables;

use App\Enums\PaymentMethod;
use App\Enums\SaleInvoiceReturnStatus;
use App\Enums\SaleInvoiceStatus;
use App\Filament\Resources\SaleReturnInvoices\SaleReturnInvoiceResource;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
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

                TextColumn::make(lang_suffix('store.name'))
                    ->label(__('sale_invoice.store'))
                    ->visible(fn (): bool => $user->isCompanyLevel())
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->searchable(['name_en', 'name_ar'])
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

                TextColumn::make('grand_total_discount')
                    ->label(__('sale_invoice.grand_total_discount'))
                    ->badge()
                    ->color('danger')
                    ->numeric(decimalPlaces: 2, locale: 'en')
                    ->prefix($currencySymbol.' ')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('extra_items_total')
                    ->label(__('app.extra_items_total'))
                    ->badge()
                    ->color('info')
                    ->numeric(decimalPlaces: 2, locale: 'en')
                    ->prefix($currencySymbol.' ')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_amount')
                    ->label(__('sale_invoice.total_amount'))
                    ->badge()
                    ->color('success')
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
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('finalizedBy.name')
                    ->label(__('sale_invoice.finalized_by'))
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('finalized_at')
                    ->label(__('sale_invoice.finalized_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('app.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label(__('sale_invoice.store'))
                    ->relationship('store', lang_suffix('name'))
                    ->multiple()
                    ->searchable(['name_en', 'name_ar'])
                    ->preload()
                    ->visible(fn (): bool => $user->isCompanyLevel())
                    ->native(false),

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
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['invoice_number'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.invoice_number').': '.$data['invoice_number'])
                                ->removeField('invoice_number');
                        }

                        return $indicators;
                    }),

                SelectFilter::make('created_by')
                    ->label(__('sale_invoice.created_by'))
                    ->relationship('createdBy', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('finalized_by')
                    ->label(__('sale_invoice.finalized_by'))
                    ->relationship('finalizedBy', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false),

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

                TernaryFilter::make('has_notes')
                    ->label(__('sale_invoice.has_notes'))
                    ->placeholder(__('app.all'))
                    ->trueLabel(__('sale_invoice.with_notes'))
                    ->falseLabel(__('sale_invoice.without_notes'))
                    ->queries(
                        true: fn (Builder $query) => $query->hasNotes(),
                        false: fn (Builder $query) => $query->withoutNotes(),
                        blank: fn (Builder $query) => $query,
                    ),

                SelectFilter::make('status')
                    ->label(__('sale_invoice.status'))
                    ->multiple()
                    ->options(SaleInvoiceStatus::class)
                    ->native(false),

                SelectFilter::make('return_status')
                    ->label(__('sale_invoice.return_status'))
                    ->multiple()
                    ->options(SaleInvoiceReturnStatus::class)
                    ->native(false),

                SelectFilter::make('payment_method')
                    ->label(__('sale_invoice.payment_method'))
                    ->multiple()
                    ->options(PaymentMethod::class)
                    ->native(false),

                SelectFilter::make('customer_id')
                    ->label(__('customer.model_label'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

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
                                'customer',
                                fn (Builder $customerQuery) => $customerQuery->where('phone', 'like', "{$phone}%")
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

                Filter::make('product_name')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('product.model_label')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['name'] ?? null,
                            fn (Builder $query, $name): Builder => $query->whereHas(
                                'items.variant', fn (Builder $variantQuery) => $variantQuery
                                    ->whereNameLike($name)
                                    ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->whereNameLike($name))
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

                Filter::make('grand_total_discount')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_discount')
                                    ->label(__('sale_invoice.discount_min'))
                                    ->numeric(),
                                TextInput::make('max_discount')
                                    ->label(__('sale_invoice.discount_max'))
                                    ->numeric(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_discount'],
                                fn (Builder $query, $amount): Builder => $query->where('grand_total_discount', '>=', $amount),
                            )
                            ->when(
                                $data['max_discount'],
                                fn (Builder $query, $amount): Builder => $query->where('grand_total_discount', '<=', $amount),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['min_discount'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.discount_min').': '.$data['min_discount'])
                                ->removeField('min_discount');
                        }
                        if ($data['max_discount'] ?? null) {
                            $indicators[] = Indicator::make(__('sale_invoice.discount_max').': '.$data['max_discount'])
                                ->removeField('max_discount');
                        }

                        return $indicators;
                    }),

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
                            ->when($data['min_amount'],
                                fn (Builder $query, $amount): Builder => $query->where('total_amount', '>=', $amount),
                            )
                            ->when($data['max_amount'],
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
                                    ->label(__('sale_invoice.created_from'))
                                    ->format('Y-m-d')
                                    ->displayFormat('Y-m-d'),
                                DatePicker::make('created_until')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('sale_invoice.created_until'))
                                    ->format('Y-m-d')
                                    ->displayFormat('Y-m-d'),
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
                                    ->label(__('sale_invoice.finalized_from'))
                                    ->format('Y-m-d')
                                    ->displayFormat('Y-m-d'),
                                DatePicker::make('finalized_until')
                                    ->native(false)
                                    ->locale(app()->getLocale())
                                    ->label(__('sale_invoice.finalized_until'))
                                    ->format('Y-m-d')
                                    ->displayFormat('Y-m-d'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['finalized_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('finalized_at', '>=', $date),
                            )
                            ->when($data['finalized_until'],
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
                        ->successNotificationTitle(__('sale_invoice.finalized_success'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->authorize('finalize_sale_invoice')
                        ->visible(fn (SaleInvoice $record): bool => ! $record->isFinalized())
                        ->action(function (SaleInvoice $record) {
                            try {
                                SaleInvoiceService::make()->finalize($record);
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
                        ->authorize('view_sale_invoice'),

                    Action::make('create_return')
                        ->label(__('app.create_return'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->url(fn (SaleInvoice $record): string => SaleReturnInvoiceResource::getUrl('create', ['original_invoice_id' => $record->id]))
                        ->visible(fn (SaleInvoice $record): bool => $record->isFinalized()),
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
