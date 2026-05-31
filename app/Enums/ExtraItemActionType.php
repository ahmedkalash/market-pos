<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExtraItemActionType: string implements HasColor, HasLabel
{
    case Addition = 'addition';
    case Subtraction = 'subtraction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Addition => __('extra_item.action_type_addition'),
            self::Subtraction => __('extra_item.action_type_subtraction'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Addition => 'success',
            self::Subtraction => 'danger',
        };
    }
}
