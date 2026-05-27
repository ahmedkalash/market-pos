<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SaleInvoiceReturnStatus: string implements HasColor, HasLabel
{
    case None = 'none';
    case PartiallyReturned = 'partially_returned';
    case FullyReturned = 'fully_returned';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => __('sale_invoice.return_status_none'),
            self::PartiallyReturned => __('sale_invoice.return_status_partially_returned'),
            self::FullyReturned => __('sale_invoice.return_status_fully_returned'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::PartiallyReturned => 'warning',
            self::FullyReturned => 'danger',
        };
    }
}
