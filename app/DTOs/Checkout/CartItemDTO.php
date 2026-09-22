<?php

namespace App\DTOs\Checkout;

use App\Enums\DiscountType;
use App\Enums\PriceType;

readonly class CartItemDTO
{
    public function __construct(
        public int $variantId,
        public float $quantity,
        public PriceType $priceType,
        public ?DiscountType $discountType = null,
        public float $discountAmount = 0.0,
        public float $unitPrice = 0.0,
    ) {}

    /**
     * Create a CartItemDTO from an array (e.g. from POS Terminal client payload).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawPriceType = $data['price_type'] ?? null;
        $priceType = $rawPriceType instanceof PriceType
            ? $rawPriceType
            : PriceType::from((string) $rawPriceType);

        $rawDiscountType = $data['discount_type'] ?? null;
        $discountType = match (true) {
            $rawDiscountType instanceof DiscountType => $rawDiscountType,
            filled($rawDiscountType) => DiscountType::from((string) $rawDiscountType),
            default => null,
        };

        return new self(
            variantId: (int) ($data['variant_id']),
            quantity: (float) ($data['qty']),
            priceType: $priceType,
            discountType: $discountType,
            discountAmount: (float) ($data['discount_amount'] ?? 0.0),
            unitPrice: (float) ($data['unit_price'] ?? 0.0),
        );
    }
}
