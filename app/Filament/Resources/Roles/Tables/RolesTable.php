<?php

namespace App\Filament\Resources\Roles\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.name'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (string $state) {
                        // Check if it's a standard role to translate it gracefully
                        if (in_array($state, array_keys(config('company_standard_roles.roles', [])))) {
                            return __('roles.'.$state);
                        }

                        return $state;
                    })
                    ->badge(fn (string $state) => in_array($state, array_keys(config('company_standard_roles.roles', []))))
                    ->color(fn (string $state) => in_array($state, array_keys(config('company_standard_roles.roles', []))) ? 'primary' : null),

                TextColumn::make('permissions_count')
                    ->label(__('app.permissions'))
                    ->counts('permissions')
                    ->badge()
                    ->color(Color::Green),

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
                    DeleteAction::make()
                        ->hidden(fn (Role $record) => in_array($record->name, array_keys(config('company_standard_roles.roles', [])))),
                ])->badge(),
            ])
            ->toolbarActions([
                // Avoid bulk delete out of safety, or implement custom logic later.
            ]);
    }
}
