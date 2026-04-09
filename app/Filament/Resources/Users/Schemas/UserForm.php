<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.user_details'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.full_name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('app.email_address'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label(__('app.password'))
                            ->password()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->visible(fn(string $operation): bool => $operation === 'create'),

                        TextInput::make('phone')
                            ->label(__('app.phone'))
                            ->tel(),

                        Toggle::make('is_active')
                            ->label(__('app.active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make(__('app.role_and_assignment'))
                    ->schema([
                        Select::make('role')
                            ->label(__('app.role'))
                            ->options(
                                Role::query()
                                    ->where('name', '!=', 'Super Admin')
                                    ->pluck('name', 'name')
                            )
                            ->required()
                            ->live(),

                        Select::make('store_id')
                            ->label(__('app.assigned_store'))
                            ->relationship('store', 'name_en')
                            ->visible(fn(Get $get): bool => in_array($get('role'), [
                                'Store Manager',
                                'Cashier',
                                'Stock Clerk',
                            ]))
                            ->required(fn(Get $get): bool => in_array($get('role'), [
                                'Store Manager',
                                'Cashier',
                                'Stock Clerk',
                            ])),
                    ])
                    ->columns(2),
            ]);
    }
}
