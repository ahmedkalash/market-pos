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
use Illuminate\Database\Eloquent\Model;
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
                Section::make(__('product.general_information'))
                    ->compact()
                    ->schema([
                        TextInput::make('name_ar')
                            ->label(__('product.name_arabic'))
                            ->helperText(__('product.name_ar_helper'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_en')
                            ->label(__('product.name_english'))
                            ->helperText(__('product.name_en_helper'))
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description_ar')
                            ->label(__('product.description_ar'))
                            ->helperText(__('product.description_ar_helper'))
                            ->rows(2),

                        Textarea::make('description_en')
                            ->label(__('product.description_en'))
                            ->helperText(__('product.description_en_helper'))
                            ->rows(2),
                    ])
                    ->columns(2),

                // Sidebar (1/3)
                Section::make(__('product.organization'))
                    ->compact()
                    ->schema([
                        Select::make('store_id')
                            ->label(__('app.store'))
                            ->relationship('store', lang_suffix('name'), fn (Builder $query, ?Model $record) => $query->active($record?->store_id))
                            ->required()
                            ->searchable(['name_en', 'name_ar'])
                            ->preload()
                            ->visible(fn () => $user->isCompanyLevel())
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation !== 'edit'),

                        Hidden::make('store_id')
                            ->default($user->store_id)
                            ->visible(fn () => $user->isStoreLevel())
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation !== 'edit'),

                        Select::make('category_id')
                            ->label(__('product_category.category'))
                            ->helperText(__('product_category.category_helper'))
                            ->relationship('category', lang_suffix('name'), fn (Builder $query, ?Model $record) => $query->active($record?->category_id))
                            ->searchable(['name_en', 'name_ar'])
                            ->preload()
                            ->nullable(),

                        Select::make('tax_class_id')
                            ->label(__('tax_class.tax_class'))
                            ->helperText(__('tax_class.tax_class_helper'))
                            ->relationship('taxClass', lang_suffix('name'))
                            ->required()
                            ->searchable(['name_en', 'name_ar'])
                            ->preload(),

                        Select::make('brand_id')
                            ->label(__('brand.brand'))
                            ->relationship('brand', lang_suffix('name'), fn (Builder $query, ?Model $record) => $query->active($record?->brand_id))
                            ->searchable(['name_en', 'name_ar'])
                            ->preload()
                            ->nullable(),

                    ])
                    ->columns(2),

            ]);
    }
}
