<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Shrinkage = 'shrinkage';
    case StocktakeCorrection = 'stocktake_correction';
    case OpeningStock = 'opening_stock';
    case Other = 'other';
}
