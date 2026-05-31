<?php

namespace App\Filament\Resources\SaleReturnInvoices;

use App\Enums\SaleReturnStatus;
use App\Filament\Resources\SaleReturnInvoices\Pages\CreateSaleReturnInvoice;
use App\Filament\Resources\SaleReturnInvoices\Pages\EditSaleReturnInvoice;
use App\Filament\Resources\SaleReturnInvoices\Pages\ListSaleReturnInvoices;
use App\Filament\Resources\SaleReturnInvoices\Pages\ViewSaleReturnInvoice;
use App\Filament\Resources\SaleReturnInvoices\Schemas\SaleReturnInvoiceForm;
use App\Filament\Resources\SaleReturnInvoices\Tables\SaleReturnInvoicesTable;
use App\Models\SaleReturnInvoice;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SaleReturnInvoiceResource extends Resource
{
    protected static ?string $model = SaleReturnInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'return_number';

    public static function getNavigationGroup(): ?string
    {
        return __('sale_invoice.sales');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.sale_returns');
    }

    public static function getModelLabel(): string
    {
        return __('app.sale_return');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.sale_returns');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', SaleReturnStatus::Draft)->count();

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

        return $user && $user->can('view_any_sale_return');
    }

    public static function canView(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('view_sale_return');
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('create_sale_return');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var SaleReturnInvoice $record */
        return $user && $user->can('update_sale_return') && ! $record->isFinalized();
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var SaleReturnInvoice $record */
        return $user && $user->can('delete_sale_return') && ! $record->isFinalized();
    }

    public static function canDeleteAny(): bool
    {
        return false; // Bulk delete disabled
    }

    public static function form(Schema $schema): Schema
    {
        return SaleReturnInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SaleReturnInvoicesTable::configure($table);
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
            'index' => ListSaleReturnInvoices::route('/'),
            'create' => CreateSaleReturnInvoice::route('/create'),
            'view' => ViewSaleReturnInvoice::route('/{record}'),
            'edit' => EditSaleReturnInvoice::route('/{record}/edit'),
        ];
    }
}
