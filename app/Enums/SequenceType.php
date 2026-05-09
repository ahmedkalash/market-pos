<?php

namespace App\Enums;

enum SequenceType: string
{
    case PurchaseInvoice = 'purchase_invoice';

    public function getPrefix(): string
    {
        return match ($this) {
            self::PurchaseInvoice => 'PI',
        };
    }
}
