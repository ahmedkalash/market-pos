<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnInvoice;
use App\Models\SaleReturnInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnInvoiceItem>
 */
class SaleReturnInvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_return_invoice_id' => SaleReturnInvoice::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'original_item_id' => SaleInvoiceItem::factory(),
            'quantity' => $this->faker->randomFloat(3, 1, 10),
            'unit_price' => $this->faker->randomFloat(4, 10, 100),
            'unit_discount_amount' => 0,
            'prorated_global_discount' => 0,
            'effective_unit_refund' => $this->faker->randomFloat(4, 10, 100),
            'item_refund_total' => $this->faker->randomFloat(2, 10, 1000),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
