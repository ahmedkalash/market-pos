<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Checkout\CartItemDTO;
use App\DTOs\Checkout\CheckoutMetaDataDTO;
use App\DTOs\Checkout\ExtraItemDTO;
use App\Enums\DiscountType;
use App\Enums\ExtraItemActionType;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use Tests\TestCase;

class CheckoutDTOTest extends TestCase
{
    // ==========================================
    // CartItemDTO Tests
    // ==========================================

    public function test_cart_item_dto_direct_instantiation(): void
    {
        $dto = new CartItemDTO(
            variantId: 101,
            quantity: 3.5,
            priceType: PriceType::Wholesale,
            unitPrice: 45.0,
            discountType: DiscountType::Percentage,
            discountAmount: 15.0
        );

        $this->assertSame(101, $dto->variantId);
        $this->assertSame(3.5, $dto->quantity);
        $this->assertSame(PriceType::Wholesale, $dto->priceType);
        $this->assertSame(45.0, $dto->unitPrice);
        $this->assertSame(DiscountType::Percentage, $dto->discountType);
        $this->assertSame(15.0, $dto->discountAmount);
    }

    public function test_cart_item_dto_from_array_with_standard_keys(): void
    {
        $data = [
            'variant_id' => 42,
            'qty' => 2.0,
            'price_type' => 'wholesale',
            'unit_price' => 25.0,
            'discount_type' => 'fixed',
            'discount_amount' => 5.0,
        ];

        $dto = CartItemDTO::fromArray($data);

        $this->assertSame(42, $dto->variantId);
        $this->assertSame(2.0, $dto->quantity);
        $this->assertSame(PriceType::Wholesale, $dto->priceType);
        $this->assertSame(25.0, $dto->unitPrice);
        $this->assertSame(DiscountType::Fixed, $dto->discountType);
        $this->assertSame(5.0, $dto->discountAmount);
    }

    public function test_cart_item_dto_from_array_without_discount_defaults_to_null(): void
    {
        $dto = CartItemDTO::fromArray([
            'variant_id' => 99,
            'qty' => 5.0,
            'price_type' => 'retail',
            'unit_price' => 12.50,
        ]);

        $this->assertSame(99, $dto->variantId);
        $this->assertSame(5.0, $dto->quantity);
        $this->assertSame(PriceType::Retail, $dto->priceType);
        $this->assertSame(12.50, $dto->unitPrice);
        $this->assertNull($dto->discountType);
        $this->assertNull($dto->discountAmount);
    }

    public function test_cart_item_dto_from_array_with_enum_instances_directly(): void
    {
        $data = [
            'variant_id' => 15,
            'qty' => 1.0,
            'price_type' => PriceType::Wholesale,
            'unit_price' => 30.0,
            'discount_type' => DiscountType::Percentage,
            'discount_amount' => 10,
        ];

        $dto = CartItemDTO::fromArray($data);

        $this->assertSame(PriceType::Wholesale, $dto->priceType);
        $this->assertSame(DiscountType::Percentage, $dto->discountType);
        $this->assertSame(10.0, $dto->discountAmount);
    }

    public function test_cart_item_dto_from_array_throws_when_variant_id_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('variant_id is required');

        CartItemDTO::fromArray([
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
        ]);
    }

    public function test_cart_item_dto_from_array_throws_when_qty_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('qty is required');

        CartItemDTO::fromArray([
            'variant_id' => 1,
            'price_type' => 'retail',
            'unit_price' => 10.0,
        ]);
    }

    public function test_cart_item_dto_from_array_throws_when_qty_is_zero_or_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('qty is required and must be greater than 0');

        CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
        ]);
    }

    public function test_cart_item_dto_from_array_throws_when_price_type_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('price_type is required');

        CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'unit_price' => 10.0,
        ]);
    }

    public function test_cart_item_dto_from_array_throws_on_invalid_price_type(): void
    {
        $this->expectException(\ValueError::class);

        CartItemDTO::fromArray([
            'variant_id' => 77,
            'qty' => 1.0,
            'price_type' => 'non_existent_price_type',
            'unit_price' => 10.0,
        ]);
    }

    public function test_cart_item_dto_from_array_without_unit_price_defaults_to_zero(): void
    {
        $dto = CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
        ]);

        $this->assertSame(0.0, $dto->unitPrice);
    }

    public function test_cart_item_dto_from_array_throws_when_unit_price_is_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unit_price must be a non-negative number');

        CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => -5.0,
        ]);
    }

    public function test_cart_item_dto_from_array_throws_on_invalid_discount_type(): void
    {
        $this->expectException(\ValueError::class);

        CartItemDTO::fromArray([
            'variant_id' => 77,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_type' => 'unknown_discount',
        ]);
    }

    public function test_cart_item_dto_from_array_throws_when_discount_type_given_without_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discount_amount is required when discount_type is specified');

        CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_type' => 'fixed',
        ]);
    }

    public function test_cart_item_dto_from_array_throws_when_discount_amount_given_without_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discount_type is required when discount_amount is specified');

        CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_amount' => 5.0,
        ]);
    }

    public function test_cart_item_dto_from_array_throws_when_discount_amount_is_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discount_amount cannot be negative');

        CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_type' => 'fixed',
            'discount_amount' => -2.0,
        ]);
    }

    public function test_cart_item_dto_from_array_normalizes_empty_string_discount_amount_to_null(): void
    {
        $dto = CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_amount' => '',
        ]);

        $this->assertNull($dto->discountAmount);
        $this->assertNull($dto->discountType);
    }

    public function test_cart_item_dto_from_array_normalizes_zero_integer_discount_without_type_to_null(): void
    {
        $dto = CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_amount' => 0,
        ]);

        $this->assertNull($dto->discountAmount);
        $this->assertNull($dto->discountType);
    }

    public function test_cart_item_dto_from_array_normalizes_zero_string_discount_without_type_to_null(): void
    {
        $dto = CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_amount' => '0',
        ]);

        $this->assertNull($dto->discountAmount);
        $this->assertNull($dto->discountType);
    }

    public function test_cart_item_dto_from_array_casts_numeric_string_discount_to_float(): void
    {
        $dto = CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_type' => 'fixed',
            'discount_amount' => '15.5',
        ]);

        $this->assertSame(15.5, $dto->discountAmount);
        $this->assertSame(DiscountType::Fixed, $dto->discountType);
    }

    public function test_cart_item_dto_from_array_throws_when_discount_amount_is_not_numeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discount_amount must be numeric.');

        CartItemDTO::fromArray([
            'variant_id' => 1,
            'qty' => 1.0,
            'price_type' => 'retail',
            'unit_price' => 10.0,
            'discount_type' => 'fixed',
            'discount_amount' => 'abc_invalid',
        ]);
    }

    public function test_cart_item_dto_constructor_throws_on_invalid_invariants(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discount_amount cannot exceed 100% for percentage discounts');

        new CartItemDTO(
            variantId: 1,
            quantity: 1.0,
            priceType: PriceType::Retail,
            unitPrice: 10.0,
            discountType: DiscountType::Percentage,
            discountAmount: 150.0
        );
    }

    // ==========================================
    // ExtraItemDTO Tests
    // ==========================================

    public function test_extra_item_dto_direct_instantiation(): void
    {
        $dto = new ExtraItemDTO(
            name: 'Express Packaging',
            actionType: ExtraItemActionType::Addition,
            amount: 12.50,
            notes: 'Handled with care'
        );

        $this->assertSame('Express Packaging', $dto->name);
        $this->assertSame(ExtraItemActionType::Addition, $dto->actionType);
        $this->assertSame(12.50, $dto->amount);
        $this->assertSame('Handled with care', $dto->notes);
    }

    public function test_extra_item_dto_from_array_with_valid_data(): void
    {
        $data = [
            'name' => 'Promotion Discount',
            'action_type' => 'subtraction',
            'amount' => 8.0,
            'notes' => 'Coupon code #123',
        ];

        $dto = ExtraItemDTO::fromArray($data);

        $this->assertSame('Promotion Discount', $dto->name);
        $this->assertSame(ExtraItemActionType::Subtraction, $dto->actionType);
        $this->assertSame(8.0, $dto->amount);
        $this->assertSame('Coupon code #123', $dto->notes);
    }

    public function test_extra_item_dto_from_array_handles_enum_instance_and_empty_notes(): void
    {
        $data = [
            'name' => 'Extra Fee',
            'action_type' => ExtraItemActionType::Addition,
            'amount' => 5.0,
            'notes' => '   ', // Blank whitespace should be converted to null
        ];

        $dto = ExtraItemDTO::fromArray($data);

        $this->assertSame('Extra Fee', $dto->name);
        $this->assertSame(ExtraItemActionType::Addition, $dto->actionType);
        $this->assertSame(5.0, $dto->amount);
        $this->assertNull($dto->notes);
    }

    public function test_extra_item_dto_from_array_throws_when_name_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required.');

        ExtraItemDTO::fromArray([
            'action_type' => 'addition',
            'amount' => 10.0,
        ]);
    }

    public function test_extra_item_dto_from_array_throws_when_name_is_blank(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name is required.');

        ExtraItemDTO::fromArray([
            'name' => '   ',
            'action_type' => 'addition',
            'amount' => 10.0,
        ]);
    }

    public function test_extra_item_dto_from_array_throws_when_action_type_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('action_type is required.');

        ExtraItemDTO::fromArray([
            'name' => 'Gift Wrap',
            'amount' => 10.0,
        ]);
    }

    public function test_extra_item_dto_from_array_throws_when_action_type_is_blank(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('action_type is required.');

        ExtraItemDTO::fromArray([
            'name' => 'Gift Wrap',
            'action_type' => '',
            'amount' => 10.0,
        ]);
    }

    public function test_extra_item_dto_from_array_throws_when_amount_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('amount is required.');

        ExtraItemDTO::fromArray([
            'name' => 'Gift Wrap',
            'action_type' => 'addition',
        ]);
    }

    public function test_extra_item_dto_from_array_throws_when_amount_is_not_numeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('amount is required.');

        ExtraItemDTO::fromArray([
            'name' => 'Gift Wrap',
            'action_type' => 'addition',
            'amount' => 'invalid_amount',
        ]);
    }

    public function test_extra_item_dto_from_array_throws_on_invalid_action_type(): void
    {
        $this->expectException(\ValueError::class);

        ExtraItemDTO::fromArray([
            'name' => 'Bad Item',
            'action_type' => 'multiply_invalid',
            'amount' => 10.0,
        ]);
    }

    // ==========================================
    // CheckoutMetaDataDTO Tests
    // ==========================================

    public function test_checkout_meta_data_dto_direct_instantiation(): void
    {
        $extraItem = new ExtraItemDTO('Wrap', ExtraItemActionType::Addition, 5.0);

        $dto = new CheckoutMetaDataDTO(
            storeId: 1,
            companyId: 2,
            customerId: 3,
            paymentMethod: PaymentMethod::Card,
            globalDiscountType: DiscountType::Fixed,
            globalDiscountAmount: 10.0,
            shippingDestinationId: 4,
            shippingCost: 15.0,
            shippingAddress: '123 Main St',
            extraItems: [$extraItem]
        );

        $this->assertSame(1, $dto->storeId);
        $this->assertSame(2, $dto->companyId);
        $this->assertSame(3, $dto->customerId);
        $this->assertSame(PaymentMethod::Card, $dto->paymentMethod);
        $this->assertSame(DiscountType::Fixed, $dto->globalDiscountType);
        $this->assertSame(10.0, $dto->globalDiscountAmount);
        $this->assertSame(4, $dto->shippingDestinationId);
        $this->assertSame(15.0, $dto->shippingCost);
        $this->assertSame('123 Main St', $dto->shippingAddress);
        $this->assertCount(1, $dto->extraItems);
        $this->assertSame($extraItem, $dto->extraItems[0]);
    }

    public function test_checkout_meta_data_dto_from_array_with_nested_arrays(): void
    {
        $data = [
            'store_id' => 5,
            'company_id' => 10,
            'customer_id' => 20,
            'payment_method' => 'card',
            'global_discount_type' => 'percentage',
            'global_discount_amount' => 10,
            'shipping_destination_id' => 2,
            'shipping_cost' => 25.50,
            'shipping_address' => 'District 5, Cairo',
            'extra_items' => [
                [
                    'name' => 'Gift Box',
                    'action_type' => 'addition',
                    'amount' => 15.00,
                ],
                [
                    'name' => 'VIP Discount',
                    'action_type' => 'subtraction',
                    'amount' => 5.00,
                ],
            ],
        ];

        $dto = CheckoutMetaDataDTO::fromArray($data);

        $this->assertSame(5, $dto->storeId);
        $this->assertSame(10, $dto->companyId);
        $this->assertSame(20, $dto->customerId);
        $this->assertSame(PaymentMethod::Card, $dto->paymentMethod);
        $this->assertSame(DiscountType::Percentage, $dto->globalDiscountType);
        $this->assertSame(10.0, $dto->globalDiscountAmount);
        $this->assertSame(2, $dto->shippingDestinationId);
        $this->assertSame(25.50, $dto->shippingCost);
        $this->assertSame('District 5, Cairo', $dto->shippingAddress);

        $this->assertCount(2, $dto->extraItems);
        $this->assertInstanceOf(ExtraItemDTO::class, $dto->extraItems[0]);
        $this->assertSame('Gift Box', $dto->extraItems[0]->name);
        $this->assertSame(ExtraItemActionType::Addition, $dto->extraItems[0]->actionType);
        $this->assertSame(15.00, $dto->extraItems[0]->amount);

        $this->assertInstanceOf(ExtraItemDTO::class, $dto->extraItems[1]);
        $this->assertSame('VIP Discount', $dto->extraItems[1]->name);
        $this->assertSame(ExtraItemActionType::Subtraction, $dto->extraItems[1]->actionType);
        $this->assertSame(5.00, $dto->extraItems[1]->amount);
    }

    public function test_checkout_meta_data_dto_from_array_with_minimal_payload_uses_defaults(): void
    {
        $dto = CheckoutMetaDataDTO::fromArray([
            'store_id' => 1,
            'company_id' => 2,
            'payment_method' => 'cash',
        ]);

        $this->assertSame(1, $dto->storeId);
        $this->assertSame(2, $dto->companyId);
        $this->assertSame(PaymentMethod::Cash, $dto->paymentMethod);
        $this->assertNull($dto->customerId);
        $this->assertNull($dto->globalDiscountType);
        $this->assertNull($dto->globalDiscountAmount);
        $this->assertNull($dto->shippingDestinationId);
        $this->assertNull($dto->shippingCost);
        $this->assertNull($dto->shippingAddress);
        $this->assertSame([], $dto->extraItems);
    }

    public function test_checkout_meta_data_dto_from_array_throws_when_store_id_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('store_id is required.');

        CheckoutMetaDataDTO::fromArray([
            'company_id' => 1,
            'payment_method' => 'cash',
        ]);
    }

    public function test_checkout_meta_data_dto_from_array_throws_when_company_id_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('company_id is required.');

        CheckoutMetaDataDTO::fromArray([
            'store_id' => 1,
            'payment_method' => 'cash',
        ]);
    }

    public function test_checkout_meta_data_dto_from_array_throws_when_payment_method_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('payment_method is required.');

        CheckoutMetaDataDTO::fromArray([
            'store_id' => 1,
            'company_id' => 1,
        ]);
    }

    public function test_checkout_meta_data_dto_from_array_with_discount_keys(): void
    {
        $data = [
            'store_id' => 1,
            'company_id' => 1,
            'payment_method' => 'cash',
            'global_discount_amount' => 12.0,
            'global_discount_type' => 'fixed',
        ];

        $dto = CheckoutMetaDataDTO::fromArray($data);

        $this->assertSame(12.0, $dto->globalDiscountAmount);
        $this->assertSame(DiscountType::Fixed, $dto->globalDiscountType);
        $this->assertSame(PaymentMethod::Cash, $dto->paymentMethod);
    }

    public function test_checkout_meta_data_dto_handles_already_instantiated_extra_items(): void
    {
        $extraItem = new ExtraItemDTO('Custom Preset', ExtraItemActionType::Addition, 10.0);

        $data = [
            'store_id' => 1,
            'company_id' => 1,
            'payment_method' => 'card',
            'extra_items' => [$extraItem],
        ];

        $dto = CheckoutMetaDataDTO::fromArray($data);

        $this->assertCount(1, $dto->extraItems);
        $this->assertSame($extraItem, $dto->extraItems[0]);
        $this->assertSame(PaymentMethod::Card, $dto->paymentMethod);
    }

    public function test_checkout_meta_data_dto_from_array_throws_on_invalid_payment_method(): void
    {
        $this->expectException(\ValueError::class);

        CheckoutMetaDataDTO::fromArray([
            'store_id' => 1,
            'company_id' => 1,
            'payment_method' => 'unsupported_method',
        ]);
    }

    public function test_checkout_meta_data_dto_from_array_throws_on_invalid_discount_type(): void
    {
        $this->expectException(\ValueError::class);

        CheckoutMetaDataDTO::fromArray([
            'store_id' => 1,
            'company_id' => 1,
            'payment_method' => 'cash',
            'global_discount_type' => 'unsupported_discount',
        ]);
    }
}
