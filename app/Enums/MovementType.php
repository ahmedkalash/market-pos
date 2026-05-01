<?php

namespace App\Enums;

enum MovementType: string
{
    case StockIn = 'stock_in';
    case Sale = 'sale';
    case Return = 'return';
    case AdjustmentAdd = 'adjustment_add';
    case AdjustmentSub = 'adjustment_sub';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case OpeningStock = 'opening_stock';
}
