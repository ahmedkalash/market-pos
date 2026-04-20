<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Enums\Roles;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordActionsColumnLabel(__('app.actions'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')
                    ->label(__('app.image'))
                    ->collection('images')
                    ->conversion('thumb')
                    ->circular()
                    ->stacked(),

                TextColumn::make('name_en')
                    ->label(__('app.name_en'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_ar')
                    ->label(__('app.name_ar'))
                    ->searchable(),

                TextColumn::make('managers')
                    ->label(__('app.managers'))
                    ->state(fn ($record) => $record->users()
                        ->role(Roles::STORE_MANAGER->value)
                        ->pluck('name')
                        ->toArray())
                    ->badge()
                    ->listWithLineBreaks()
                    ->color('info'),

                TextColumn::make('phone')
                    ->label(__('app.phone'))
                    ->searchable()
                    ->copyable()
                    ->tooltip(__('app.click_to_copy_item')),

                IconColumn::make('is_active')
                    ->label(__('app.active'))
                    ->boolean()
                    ->width('80px'),

                TextColumn::make('created_at')
                    ->label(__('app.created'))
                    ->dateTime('j - M - Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->label(null)
                    ->tooltip(__('app.actions')),
            ])
            ->toolbarActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }
}
