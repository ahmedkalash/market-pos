<?php

namespace App\DTOs\Checkout;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use InvalidArgumentException;

readonly class CheckoutMetaDataDTO
{
    /**
     * @param  array<ExtraItemDTO>  $extraItems
     */
    public function __construct(
        public int $storeId,
        public int $companyId,
        public PaymentMethod $paymentMethod,
        public ?int $customerId = null,
        public ?DiscountType $globalDiscountType = null,
        public ?float $globalDiscountAmount = null,
        public ?int $shippingDestinationId = null,
        public ?float $shippingCost = null,
        public ?string $shippingAddress = null,
        public array $extraItems = [],
    ) {}

    /**
     * Create a CheckoutMetaDataDTO from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['store_id']) || $data['store_id'] == '') {
            throw new InvalidArgumentException('store_id is required.');
        }

        if (! isset($data['company_id']) || $data['company_id'] == '') {
            throw new InvalidArgumentException('company_id is required.');
        }

        if (! isset($data['payment_method']) || $data['payment_method'] == '') {
            throw new InvalidArgumentException('payment_method is required.');
        }

        $paymentMethod = match (true) {
            $data['payment_method'] instanceof PaymentMethod => $data['payment_method'],
            default => PaymentMethod::from((string) $data['payment_method']),
        };

        $globalDiscountAmount = isset($data['global_discount_amount']) && $data['global_discount_amount'] !== ''
            ? (float) $data['global_discount_amount']
            : null;

        $rawDiscountType = $data['global_discount_type'] ?? null;
        $globalDiscountType = match (true) {
            $rawDiscountType instanceof DiscountType => $rawDiscountType,
            filled($rawDiscountType) => DiscountType::from((string) $rawDiscountType),
            default => null,
        };

        $shippingCost = isset($data['shipping_cost']) && $data['shipping_cost'] !== ''
            ? (float) $data['shipping_cost']
            : null;

        $extraItemsData = $data['extra_items'] ?? [];
        $extraItems = [];
        if (is_array($extraItemsData)) {
            foreach ($extraItemsData as $extraItem) {
                if ($extraItem instanceof ExtraItemDTO) {
                    $extraItems[] = $extraItem;
                } elseif (is_array($extraItem)) {
                    $extraItems[] = ExtraItemDTO::fromArray($extraItem);
                } else {
                    throw new InvalidArgumentException('Each extra item must be an instance of `ExtraItemDTO` or an `array`.');
                }
            }
        }

        return new self(
            storeId: (int) $data['store_id'],
            companyId: (int) $data['company_id'],
            paymentMethod: $paymentMethod,
            customerId: $data['customer_id'] ?? null,
            globalDiscountType: $globalDiscountType,
            globalDiscountAmount: $globalDiscountAmount,
            shippingDestinationId: $data['shipping_destination_id'] ?? null,
            shippingCost: $shippingCost,
            shippingAddress: isset($data['shipping_address']) && filled($data['shipping_address']) ? $data['shipping_address'] : null,
            extraItems: $extraItems,
        );
    }
}
