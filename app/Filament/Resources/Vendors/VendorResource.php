<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\Schemas\VendorForm;
use App\Filament\Resources\Vendors\Tables\VendorsTable;
use App\Models\User;
use App\Models\Vendor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('vendor.purchasing');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor.vendors');
    }

    public static function getModelLabel(): string
    {
        return __('vendor.vendor');
    }

    public static function getPluralModelLabel(): string
    {
        return __('vendor.vendors');
    }

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('view_any_vendor');
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('create_vendor');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('update_vendor');
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('delete_vendor');
    }

    public static function canDeleteAny(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('delete_any_vendor');
    }

    public static function form(Schema $schema): Schema
    {
        return VendorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendors::route('/'),
        ];
    }
}
