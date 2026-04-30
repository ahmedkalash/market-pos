<?php

namespace App\Filament\Resources\UnitOfMeasures;

use App\Filament\Resources\UnitOfMeasures\Pages\ListUnitOfMeasures;
use App\Filament\Resources\UnitOfMeasures\Schemas\UnitOfMeasureForm;
use App\Filament\Resources\UnitOfMeasures\Tables\UnitOfMeasuresTable;
use App\Models\UnitOfMeasure;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UnitOfMeasureResource extends Resource
{
    protected static ?string $model = UnitOfMeasure::class;

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('view_any_unit_of_measure');
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('create_unit_of_measure');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('update_unit_of_measure');
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('delete_unit_of_measure');
    }

    public static function canDeleteAny(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('delete_any_unit_of_measure');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    public static function getNavigationGroup(): ?string
    {
        return __('product.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('unit_of_measure.units_of_measure');
    }

    public static function getPluralLabel(): string
    {
        return __('unit_of_measure.units_of_measure');
    }

    public static function getModelLabel(): string
    {
        return __('unit_of_measure.unit_of_measure');
    }

    public static function form(Schema $schema): Schema
    {
        return UnitOfMeasureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitOfMeasuresTable::configure($table);
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
            'index' => ListUnitOfMeasures::route('/'),
        ];
    }
}
