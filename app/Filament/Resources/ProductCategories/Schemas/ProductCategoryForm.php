<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('catalog.product_categories.form.category_details'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name_en')
                                ->label(__('app.name_en'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('name_ar')
                                ->label(__('app.name_ar'))
                                ->required()
                                ->maxLength(255),
                            Toggle::make('is_active')
                                ->label(__('catalog.product_categories.form.active_status'))
                                ->default(true)
                                ->inline(false),
                        ]),
                        Grid::make(2)->schema([
                            Select::make('parent_id')
                                ->label(__('catalog.product_categories.form.parent_category'))
                                ->relationship(
                                    name: 'parent',
                                    titleAttribute: 'name_en',
                                    modifyQueryUsing: fn (Builder $query, Select $component) => $query
                                        ->where('id', '!=', $component->getRecord()?->id)
                                        ->when(
                                            $component->getRecord(),
                                            fn (Builder $query, $record) => $query->whereNotIn('id', $record->descendants()->withoutGlobalScope('company')->pluck('id')->toArray())
                                        )
                                )
                                ->searchable()
                                ->preload(),
                        ]),
                        Grid::make(2)->schema([
                            Textarea::make('description_en')
                                ->label(__('app.description_en'))
                                ->maxLength(65535)
                                ->columnSpan(1),
                            Textarea::make('description_ar')
                                ->label(__('app.description_ar'))
                                ->maxLength(65535)
                                ->columnSpan(1),
                        ]),
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('image')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
