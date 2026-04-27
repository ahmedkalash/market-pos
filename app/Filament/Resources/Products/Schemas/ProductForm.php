<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();
        $companyId = $user->company_id;

        return $schema
            ->components([
                // Main column (2/3)
                Section::make(__('app.general_information'))
                    ->schema([
                        TextInput::make('name_en')
                            ->label(__('app.name_english'))
                            ->helperText(__('app.name_en_helper'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_ar')
                            ->label(__('app.name_arabic'))
                            ->helperText(__('app.name_ar_helper'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description_en')
                            ->label(__('app.description_en'))
                            ->helperText(__('app.description_en_helper'))
                            ->rows(4),

                        Textarea::make('description_ar')
                            ->label(__('app.description_ar'))
                            ->helperText(__('app.description_ar_helper'))
                            ->rows(4),
                    ])
                    ->columns(2),

                // Sidebar (1/3)
                Section::make(__('app.organization'))
                    ->schema([
                        Hidden::make('store_id')
                            ->default($user->store_id),
                        Select::make('category_id')
                            ->label(__('app.category'))
                            ->helperText(__('app.category_helper'))
                            ->relationship('category', 'name_'.app()->getLocale(), fn (Builder $query) => $query->where('company_id', $companyId))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('tax_class_id')
                            ->label(__('app.tax_class'))
                            ->helperText(__('app.tax_class_helper'))
                            ->relationship('taxClass', 'name_'.app()->getLocale(), fn (Builder $query) => $query->where('company_id', $companyId))
                            ->required()
                            ->searchable()
                            ->preload(),

                    ])
                    ->columns(2),

            ]);
    }
}
