<?php

namespace App\DTOs\Checkout;

use App\Enums\DiscountType;
use App\Enums\PriceType;
use InvalidArgumentException;
use ValueError;

readonly class CartItemDTO
{
    public function __construct(
        public int $variantId,
        public float $quantity,
        public PriceType $priceType,
        public float $unitPrice,
        public ?DiscountType $discountType = null,
        public ?float $discountAmount = null,
    ) {
        if ($this->variantId <= 0) {
            throw new InvalidArgumentException('variant_id must be a positive integer.');
        }

        if ($this->quantity <= 0) {
            throw new InvalidArgumentException('quantity must be greater than 0.');
        }

        if ($this->unitPrice < 0) {
            throw new InvalidArgumentException('unit_price cannot be negative.');
        }

        if ($this->discountType !== null && $this->discountAmount === null) {
            throw new InvalidArgumentException('discount_amount is required when discount_type is specified.');
        }

        if ($this->discountAmount !== null && $this->discountType === null) {
            throw new InvalidArgumentException('discount_type is required when discount_amount is specified.');
        }

        if ($this->discountAmount !== null && $this->discountAmount < 0) {
            throw new InvalidArgumentException('discount_amount cannot be negative.');
        }

        if ($this->discountType === DiscountType::Percentage && $this->discountAmount !== null && $this->discountAmount > 100) {
            throw new InvalidArgumentException('discount_amount cannot exceed 100% for percentage discounts.');
        }
    }

    /**
     * Create a CartItemDTO from an array using strict canonical keys only.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     * @throws ValueError
     */
    public static function fromArray(array $data): self
    {
        // 1. variant_id (required, positive integer)
        if (! isset($data['variant_id']) || ! is_numeric($data['variant_id']) || (int) $data['variant_id'] <= 0) {
            throw new InvalidArgumentException('variant_id is required and must be a positive integer.');
        }

        // 2. qty (canonical key, required, gt:0)
        if (! isset($data['qty']) || ! is_numeric($data['qty']) || (float) $data['qty'] <= 0) {
            throw new InvalidArgumentException('qty is required and must be greater than 0.');
        }

        // 3. price_type (canonical key, required, valid enum)
        if (! isset($data['price_type']) || blank($data['price_type'])) {
            throw new InvalidArgumentException('price_type is required.');
        }

        $priceType = match (true) {
            $data['price_type'] instanceof PriceType => $data['price_type'],
            default => PriceType::from((string) $data['price_type']),
        };

        // 4. unit_price (canonical key, non-negative, defaults to 0.0 if not provided in cart payload)
        $unitPrice = $data['unit_price'] ?? 0.0;
        if (! is_numeric($unitPrice) || (float) $unitPrice < 0) {
            throw new InvalidArgumentException('unit_price must be a non-negative number.');
        }

        // 5. discount_type (canonical key, nullable enum)
        $discountType = $data['discount_type'] ?? null;
        $discountType = match (true) {
            $discountType instanceof DiscountType => $discountType,
            filled($discountType) => DiscountType::from((string) $discountType),
            default => null,
        };

        // 6. discount_amount (canonical key, nullable float, non-negative)
        $discountAmount = $data['discount_amount'] ?? null;

        if (! filled($discountAmount)) {
            $discountAmount = null;
        } elseif (! is_numeric($discountAmount)) {
            throw new InvalidArgumentException('discount_amount must be numeric.');
        } else {
            $discountAmount = (float) $discountAmount;
        }

        // Normalize zero discount without discount type to null
        if ($discountType === null && $discountAmount === 0.0) {
            $discountAmount = null;
        }

        // Co-dependency checks
        if ($discountType !== null && $discountAmount === null) {
            throw new InvalidArgumentException('discount_amount is required when discount_type is specified.');
        }

        if ($discountAmount !== null && $discountType === null) {
            throw new InvalidArgumentException('discount_type is required when discount_amount is specified.');
        }

        return new self(
            variantId: (int) $data['variant_id'],
            quantity: (float) $data['qty'],
            priceType: $priceType,
            unitPrice: (float) $unitPrice,
            discountType: $discountType,
            discountAmount: $discountAmount,
        );
    }
}
