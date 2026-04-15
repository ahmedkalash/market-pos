<?php

namespace App\Enums;

enum RoundingRule: string
{
    case NONE = 'none';
    case NEAREST_025 = 'nearest_025';
    case NEAREST_050 = 'nearest_050';
    case NEAREST_100 = 'nearest_100';
}
