<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $retailPrice = fake()->randomFloat(2, 10, 1000);
        $purchasePrice = $retailPrice * 0.7; // 30% margin

        return [
            'product_id' => Product::factory(),
            'company_id' => function (array $attributes) {
                $product = Product::find($attributes['product_id']);

                return $product ? $product->company_id : Company::factory();
            },
            'store_id' => function (array $attributes) {
                $product = Product::find($attributes['product_id']);

                return $product ? $product->store_id : Store::factory();
            },
            'uom_id' => UnitOfMeasure::factory(),
            'name_en' => fake()->unique()->words(2, true),
            'name_ar' => 'متغير '.fake()->unique()->word(),
            'retail_price' => $retailPrice,
            'purchase_price' => $purchasePrice,
            'quantity' => fake()->randomFloat(3, 0, 1000),
            'is_active' => true,
        ];
    }

    /**
     * Set specific stock quantity.
     */
    public function withStock(float $quantity): static
    {
        return $this->state(fn () => ['quantity' => $quantity]);
    }

    /**
     * Enable wholesale pricing.
     */
    public function wholesale(float $price = 0, float $threshold = 10): static
    {
        return $this->state(fn () => [
            'wholesale_enabled' => true,
            'wholesale_price' => $price ?: fake()->randomFloat(2, 5, 200),
            'wholesale_qty_threshold' => $threshold,
        ]);
    }
}
