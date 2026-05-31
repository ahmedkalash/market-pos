<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasColor, HasLabel
{
    case SaleInvoice = 'sale_invoice';
    case SaleReturn = 'sale_return';
    case PurchaseInvoice = 'purchase_invoice';
    case PurchaseReturn = 'purchase_return';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SaleInvoice => __('invoice.type_sale_invoice'),
            self::SaleReturn => __('invoice.type_sale_return'),
            self::PurchaseInvoice => __('invoice.type_purchase_invoice'),
            self::PurchaseReturn => __('invoice.type_purchase_return'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SaleInvoice => 'success',
            self::SaleReturn => 'danger',
            self::PurchaseInvoice => 'info',
            self::PurchaseReturn => 'warning',
        };
    }
}
