<?php

namespace App\Enums;

enum MovementType: string
{
    case StockIn = 'stock_in';
    case Sale = 'sale';
    /** Customer returns a sold item — goods come back into the store (POS) */
    case SaleReturn = 'sale_return';
    /** Store returns goods to vendor — goods physically leave the store */
    case PurchaseReturn = 'purchase_return';
    case AdjustmentAdd = 'adjustment_add';
    case AdjustmentSub = 'adjustment_sub';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case OpeningStock = 'opening_stock';

    public function getDirection(): MovementDirection
    {
        return match ($this) {
            self::StockIn,
            self::SaleReturn,
            self::AdjustmentAdd,
            self::TransferIn,
            self::OpeningStock => MovementDirection::In,

            self::PurchaseReturn,
            self::Sale,
            self::AdjustmentSub,
            self::TransferOut => MovementDirection::Out,
        };
    }

    public function getColor(): string
    {
        $direction = $this->getDirection();

        return match ($direction) {
            MovementDirection::In => 'success',
            MovementDirection::Out => 'danger',
        };
    }
}
