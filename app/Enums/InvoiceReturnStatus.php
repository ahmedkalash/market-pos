<?php

namespace App\Enums;

enum InvoiceReturnStatus: string
{
    case None = 'none';
    case PartiallyReturned = 'partially_returned';
    case FullyReturned = 'fully_returned';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => __('purchase_return.return_status_none'),
            self::PartiallyReturned => __('purchase_return.return_status_partially_returned'),
            self::FullyReturned => __('purchase_return.return_status_fully_returned'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::PartiallyReturned => 'warning',
            self::FullyReturned => 'secondary',
        };
    }
}
