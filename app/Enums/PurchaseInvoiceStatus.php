<?php

namespace App\Enums;

enum PurchaseInvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('purchase_invoice.status_draft'),
            self::Finalized => __('purchase_invoice.status_finalized'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Finalized => 'success',
        };
    }
}
