<?php

namespace App\Filament\Resources\Vendors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('vendor.name'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-office')
                    ->iconColor('primary')
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item')),
                TextColumn::make('tax_number')
                    ->label(__('vendor.tax_number'))
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item')),
                TextColumn::make('email')
                    ->label(__('vendor.email'))
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item')),
                TextColumn::make('phone')
                    ->label(__('vendor.phone'))
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item')),
                TextColumn::make('address')
                    ->label(__('vendor.address'))
                    ->searchable()
                    ->toggleable()
                    ->limit(50)
                    ->wrap()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item')),
                IconColumn::make('is_active')
                    ->label(__('vendor.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('vendor.is_active'))
                    ->trueLabel(__('vendor.filter_active'))
                    ->falseLabel(__('vendor.filter_inactive'))
                    ->placeholder(__('vendor.filter_all')),

                SelectFilter::make('has_contact_info')
                    ->label(__('vendor.filter_has_contact'))
                    ->placeholder(__('vendor.filter_all'))
                    ->options([
                        'yes' => __('vendor.filter_has_contact_yes'),
                        'no' => __('vendor.filter_has_contact_no'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        if ($data['value'] === 'yes') {
                            return $query->where(
                                fn (Builder $q) => $q->whereNotNull('email')->orWhereNotNull('phone')
                            );
                        }

                        return $query->whereNull('email')->whereNull('phone');
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::FiveExtraLarge),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
