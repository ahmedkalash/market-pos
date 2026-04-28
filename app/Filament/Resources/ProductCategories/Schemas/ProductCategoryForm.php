<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Models\User;
use Auth;
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
        /** @var User $user */
        $user = Auth::user();

        return $schema
            ->components([
                Section::make(__('product.general_information'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('store_id')
                                ->label(__('app.store'))
                                ->relationship('store', 'name_'.app()->getLocale(),
                                    fn (Builder $query) => $query->filterByCompany($user->company_id))
                                ->required(fn () => $user->isCompanyLevel())
                                ->searchable()
                                ->preload()
                                ->visible(fn () => $user->isCompanyLevel()),
                            TextInput::make('name_en')
                                ->label(__('product_category.name_en'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('name_ar')
                                ->label(__('product_category.name_ar'))
                                ->required()
                                ->maxLength(255),
                            Select::make('parent_id')
                                ->label(__('product_category.parent_category'))
                                ->relationship(
                                    name: 'parent',
                                    titleAttribute: 'name_en',
                                    modifyQueryUsing: fn (Builder $query, Select $component) => $query
                                        ->active()
                                        ->where('id', '!=', $component->getRecord()?->id)
                                        ->when(
                                            $component->getRecord(),
                                            fn (Builder $query, $record) => $query->whereNotIn('id', $record->descendants()->withoutGlobalScopes()->pluck('id')->toArray())
                                        )
                                )
                                ->searchable()
                                ->preload(),
                            Toggle::make('is_active')
                                ->label(__('product_category.active_status'))
                                ->default(true)
                                ->inline(false),
                        ]),

                        Grid::make(2)->schema([
                            Textarea::make('description_en')
                                ->label(__('product_category.description_en'))
                                ->maxLength(65535)
                                ->columnSpan(1),
                            Textarea::make('description_ar')
                                ->label(__('product_category.description_ar'))
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
