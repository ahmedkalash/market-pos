<?php

namespace App\Filament\Resources\PurchaseInvoices;

use App\Enums\PurchaseInvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Filament\Resources\PurchaseInvoices\Pages\ViewPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceForm;
use App\Filament\Resources\PurchaseInvoices\Tables\PurchaseInvoicesTable;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getNavigationGroup(): ?string
    {
        return __('purchase_invoice.purchasing');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchase_invoice.purchase_invoices');
    }

    public static function getModelLabel(): string
    {
        return __('purchase_invoice.purchase_invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchase_invoice.purchase_invoices');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', PurchaseInvoiceStatus::Draft)->count();

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

        return $user && $user->can('view_any_purchase_invoice');
    }

    public static function canView(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('view_purchase_invoice');
    }

    public static function canCreate(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user && $user->can('create_purchase_invoice');
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var PurchaseInvoice $record */
        return $user && $user->can('update_purchase_invoice') && ! $record->isFinalized();
    }

    public static function canDelete(Model $record): bool
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var PurchaseInvoice $record */
        return $user && $user->can('delete_purchase_invoice') && ! $record->isFinalized();
    }

    public static function canDeleteAny(): bool
    {
        return false; // Bulk delete disabled — too risky for financial records
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'view' => ViewPurchaseInvoice::route('/{record}'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }
}
