<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.store_details'))
                    ->schema([
                        TextInput::make('name_en')
                            ->label(__('Name (English)'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_ar')
                            ->label(__('Name (Arabic)'))
                            ->maxLength(255),

                        Textarea::make('address')
                            ->label(__('app.address'))
                            ->rows(3),

                        TextInput::make('phone')
                            ->label(__('app.phone'))
                            ->tel(),

                        TextInput::make('email')
                            ->label(__('app.email'))
                            ->email(),

                        Toggle::make('is_active')
                            ->label(__('app.active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make(__('app.working_hours'))
                    ->schema([
                        KeyValue::make('working_hours')
                            ->label(__('app.working_hours'))
                            ->keyLabel(__('app.day'))
                            ->valueLabel(__('app.hours'))
                            ->keyPlaceholder(__('app.eg_saturday'))
                            ->valuePlaceholder(__('app.eg_hours')),
                    ]),
            ]);
    }
}
