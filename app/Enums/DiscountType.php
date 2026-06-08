<?php

namespace App\Enums;

use App\Traits\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Fixed => __('sale_invoice.discount_fixed'),
            self::Percentage => __('sale_invoice.discount_percentage'),
        };
    }
}
