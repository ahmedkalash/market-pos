<?php

namespace App\Traits;

trait EnumHelpers
{
    public static function toString(self|string|null $value): ?string
    {
        return $value instanceof self ? $value->value : $value;
    }

    public static function try(self|string|null $value): ?self
    {
        if (is_null($value)) {
            return null;
        }

        return $value instanceof self ? $value : self::tryFrom($value);
    }
}
