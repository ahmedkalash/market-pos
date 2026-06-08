<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::schema(),
            ]);
    }

    public static function schema()
    {
        return Section::make()
            ->columnSpanFull()
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('customer.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('customer.email'))
                            ->email()
                            ->maxLength(255)
                            ->default(null),
                        TextInput::make('phone')
                            ->label(__('customer.phone'))
                            ->tel()
                            ->maxLength(255)
                            ->default(null),
                        TextInput::make('tax_number')
                            ->label(__('customer.tax_number'))
                            ->maxLength(255)
                            ->default(null),
                    ]),
                Textarea::make('address')
                    ->label(__('customer.address'))
                    ->maxLength(65535)
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label(__('customer.is_active'))
                    ->default(true)
                    ->required(),
            ]);
    }
}
