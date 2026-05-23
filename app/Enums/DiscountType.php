<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Fixed => __('sale_invoice.discount_fixed'),
            self::Percentage => __('sale_invoice.discount_percentage'),
        };
    }

    public static function parseValue(self|string|null $value): ?string
    {
        return $value instanceof self ? $value->value : $value;
    }
}
