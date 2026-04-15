<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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

                        TextInput::make('whatsapp_number')
                            ->label(__('store_settings.fields.whatsapp_number'))
                            ->tel(),

                        Toggle::make('is_active')
                            ->label(__('app.active'))
                            ->default(true),

                        SpatieMediaLibraryFileUpload::make('images')
                            ->label(__('app.store_images'))
                            ->collection('images')
                            ->multiple()
                            ->reorderable()
                            ->imageEditor()
                            ->columnSpanFull(),
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

                Section::make(__('store_settings.sections.receipt'))
                    ->description(__('store_settings.sections.receipt_description'))
                    ->schema([
                        Textarea::make('receipt_header')
                            ->label(__('store_settings.fields.receipt_header'))
                            ->rows(3),

                        Textarea::make('receipt_footer')
                            ->label(__('store_settings.fields.receipt_footer'))
                            ->rows(3),

                        Select::make('receipt_show_logo')
                            ->label(__('store_settings.fields.show_logo'))
                            ->options([
                                '1' => __('store_settings.inheritance.show'),
                                '0' => __('store_settings.inheritance.hide'),
                            ])
                            ->placeholder(__('store_settings.inheritance.inherit', ['value' => '...'])),

                        Select::make('receipt_show_vat_number')
                            ->label(__('store_settings.fields.show_vat_number'))
                            ->options([
                                '1' => __('store_settings.inheritance.show'),
                                '0' => __('store_settings.inheritance.hide'),
                            ])
                            ->placeholder(__('store_settings.inheritance.inherit', ['value' => '...'])),

                        Select::make('receipt_show_address')
                            ->label(__('store_settings.fields.show_address'))
                            ->options([
                                '1' => __('store_settings.inheritance.show'),
                                '0' => __('store_settings.inheritance.hide'),
                            ])
                            ->placeholder(__('store_settings.inheritance.inherit', ['value' => '...'])),
                    ])
                    ->columns(2),

                Section::make(__('store_settings.sections.regional'))
                    ->description(__('store_settings.sections.regional_description'))
                    ->schema([
                        TextInput::make('timezone')
                            ->label(__('store_settings.fields.timezone'))
                            ->placeholder(__('store_settings.inheritance.inherit', ['value' => config('app.timezone')])),

                        TextInput::make('locale')
                            ->label(__('store_settings.fields.locale'))
                            ->placeholder(__('store_settings.inheritance.inherit', ['value' => config('app.locale')])),
                    ])
                    ->columns(2),
            ]);
    }
}
