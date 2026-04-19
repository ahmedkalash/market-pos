<?php

namespace App\Filament\Resources\Stores;

use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Filament\Resources\Stores\Pages\EditStore;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Filament\Resources\Stores\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Stores\Schemas\StoreForm;
use App\Filament\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    public static function getNavigationGroup(): ?string
    {
        return __('app.store_management');
    }

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('app.stores');
    }

    public static function canView($record): bool
    {
        return auth()->user()->can('view_store');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_store');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_store');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update_store');
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete_store');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('delete_any_store');
    }

    public static function getModelLabel(): string
    {
        return __('app.store');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.stores');
    }

    public static function form(Schema $schema): Schema
    {
        return StoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
