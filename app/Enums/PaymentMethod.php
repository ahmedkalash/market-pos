<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case Split = 'split';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => __('sale_invoice.payment_method_cash'),
            self::Card => __('sale_invoice.payment_method_card'),
            self::Split => __('sale_invoice.payment_method_split'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Card => 'info',
            self::Split => 'warning',
        };
    }
}
