<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleInvoiceItem>
 */
class SaleInvoiceItemFactory extends Factory
{
    protected $model = SaleInvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(3, 1, 10);
        $unitPrice = fake()->randomFloat(2, 5, 100);
        $taxRate = 15.00; // 15% standard tax

        $subtotal = round($quantity * $unitPrice, 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $lineTotal = $subtotal + $taxAmount;

        return [
            'sale_invoice_id' => SaleInvoice::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'notes' => fake()->sentence(),
        ];
    }
}
