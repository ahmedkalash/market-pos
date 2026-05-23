<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PriceType: string implements HasColor, HasLabel
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';

    public function getLabel(): string
    {
        return match ($this) {
            self::Retail => __('sale_invoice.price_type_retail'),
            self::Wholesale => __('sale_invoice.price_type_wholesale'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Retail => 'primary',
            self::Wholesale => 'info',
        };
    }

    public static function parseValue(self|string|null $value): ?string
    {
        return $value instanceof self ? $value->value : $value;
    }
}
