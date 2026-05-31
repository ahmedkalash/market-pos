<?php

namespace App\Filament\Resources\Attributes;

use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Models\Attribute;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('view_any_attribute');
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('create_attribute');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('update_attribute');
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('delete_attribute');
    }

    public static function canDeleteAny(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('delete_any_attribute');
    }

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
                    ->relationship('store', lang_suffix('name'))
                    ->required(fn () => $user->isCompanyLevel())
                    ->searchable(['name_en', 'name_ar'])
                    ->preload()
                    ->visible(fn () => $user->isCompanyLevel()),
                Hidden::make('store_id')
                    ->default(fn () => $user->store_id)
                    ->visible(fn () => $user->isStoreLevel()),

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
                TextColumn::make(lang_suffix('store.name'))
                    ->label(__('app.store'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->searchable(['name_en', 'name_ar'])
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
                    ->relationship('store', lang_suffix('name'))
                    ->searchable(['name_en', 'name_ar'])
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
