<?php

namespace App\Filament\Resources\PurchaseReturns;

use App\Enums\PurchaseReturnStatus;
use App\Filament\Resources\PurchaseReturns\Pages\CreatePurchaseReturn;
use App\Filament\Resources\PurchaseReturns\Pages\EditPurchaseReturn;
use App\Filament\Resources\PurchaseReturns\Pages\ListPurchaseReturns;
use App\Filament\Resources\PurchaseReturns\Pages\ViewPurchaseReturn;
use App\Filament\Resources\PurchaseReturns\Schemas\PurchaseReturnForm;
use App\Filament\Resources\PurchaseReturns\Tables\PurchaseReturnsTable;
use App\Models\PurchaseReturn;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = PurchaseReturn::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'return_number';

    public static function getNavigationGroup(): ?string
    {
        return __('purchase_invoice.purchasing');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchase_return.purchase_returns');
    }

    public static function getModelLabel(): string
    {
        return __('purchase_return.purchase_return');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchase_return.purchase_returns');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', PurchaseReturnStatus::Draft)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('view_any_purchase_return');
    }

    public static function canView(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('view_purchase_return');
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('create_purchase_return');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var PurchaseReturn $record */
        return $user && $user->can('update_purchase_return') && ! $record->isFinalized();
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var PurchaseReturn $record */
        return $user && $user->can('delete_purchase_return') && ! $record->isFinalized();
    }

    public static function canDeleteAny(): bool
    {
        return false; // Bulk delete disabled — too risky for financial records
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseReturnsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseReturns::route('/'),
            'create' => CreatePurchaseReturn::route('/create'),
            'view' => ViewPurchaseReturn::route('/{record}'),
            'edit' => EditPurchaseReturn::route('/{record}/edit'),
        ];
    }
}
