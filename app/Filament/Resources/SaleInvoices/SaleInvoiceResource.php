<?php

namespace App\Filament\Resources\SaleInvoices;

use App\Enums\SaleInvoiceStatus;
use App\Filament\Resources\SaleInvoices\Pages\CreateSaleInvoice;
use App\Filament\Resources\SaleInvoices\Pages\EditSaleInvoice;
use App\Filament\Resources\SaleInvoices\Pages\ListSaleInvoices;
use App\Filament\Resources\SaleInvoices\Pages\ViewSaleInvoice;
use App\Filament\Resources\SaleInvoices\Schemas\SaleInvoiceForm;
use App\Filament\Resources\SaleInvoices\Tables\SaleInvoicesTable;
use App\Models\SaleInvoice;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SaleInvoiceResource extends Resource
{
    protected static ?string $model = SaleInvoice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getNavigationGroup(): ?string
    {
        return __('sale_invoice.sales');
    }

    public static function getNavigationLabel(): string
    {
        return __('sale_invoice.sale_invoices');
    }

    public static function getModelLabel(): string
    {
        return __('sale_invoice.sale_invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sale_invoice.sale_invoices');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', SaleInvoiceStatus::Draft)->count();

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

        return $user && $user->can('view_any_sale_invoice');
    }

    public static function canView(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('view_sale_invoice');
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('create_sale_invoice');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var SaleInvoice $record */
        return $user && $user->can('update_sale_invoice') && ! $record->isFinalized();
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var SaleInvoice $record */
        return $user && $user->can('delete_sale_invoice') && ! $record->isFinalized();
    }

    public static function canDeleteAny(): bool
    {
        return false; // Bulk delete disabled — too risky for financial records
    }

    public static function form(Schema $schema): Schema
    {
        return SaleInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SaleInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSaleInvoices::route('/'),
            'create' => CreateSaleInvoice::route('/create'),
            'view' => ViewSaleInvoice::route('/{record}'),
            'edit' => EditSaleInvoice::route('/{record}/edit'),
        ];
    }
}
