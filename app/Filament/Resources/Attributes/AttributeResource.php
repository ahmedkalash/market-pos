<?php

namespace App\Filament\Resources\Attributes;

use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Models\Attribute;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('product.catalog');
    }

    public static function getModelLabel(): string
    {
        return __('attribute.attribute');
    }

    public static function getPluralModelLabel(): string
    {
        return __('attribute.attributes');
    }


    public static function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();
        return $schema
            ->components([
                Select::make('store_id')
                    ->label(__('app.store'))
                    ->relationship('store', 'name_'.app()->getLocale(),
                        fn (Builder $query) => $query->filterByCompany($user->company_id))
                    ->required(fn () => $user->isCompanyLevel())
                    ->searchable()
                    ->preload()
                    ->visible(fn () => $user->isCompanyLevel()),

                TextInput::make('name_en')
                    ->label(__('product.name_english'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('name_ar')
                    ->label(__('product.name_arabic'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();
        return $table
            ->columns([
                TextColumn::make('store.name_'.app()->getLocale())
                    ->label(__('app.store'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => $user->isCompanyLevel()),

                TextColumn::make('name_en')
                    ->label(__('app.name_en'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_ar')
                    ->label(__('app.name_ar'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('values_count')
                    ->label(__('attribute.values'))
                    ->counts('values')
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label(__('app.store'))
                    ->relationship('store', 'name_'.app()->getLocale(),
                        fn (Builder $query) => $query->filterByCompany($user->company_id))
                    ->visible(fn () => $user->isCompanyLevel()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
        ];
    }
}
