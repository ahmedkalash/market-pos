<?php

namespace App\Enums;

enum SequenceType: string
{
    case PurchaseInvoice = 'purchase_invoice';
    case PurchaseReturn = 'purchase_return';
    case SaleInvoice = 'sale_invoice';
    case SaleReturn = 'sale_return';

    public function getPrefix(): string
    {
        return match ($this) {
            self::PurchaseInvoice => 'PI',
            self::PurchaseReturn => 'PR',
            self::SaleInvoice => 'SI',
            self::SaleReturn => 'SR',
        };
    }
}
