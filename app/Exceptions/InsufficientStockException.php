<?php

namespace App\Exceptions;

use App\Models\ProductVariant;
use RuntimeException;

/**
 * Thrown when an inventory operation attempts to withdraw more stock
 * than is currently available for a given variant.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly ProductVariant $variant,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(
            __('app.insufficient_stock_exception_message', [
                'product' => $variant->product?->name ?? $variant->id,
                'requested' => $requested,
                'available' => $available,
            ])
        );
    }
}
