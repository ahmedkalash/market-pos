<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'category_id' => ProductCategory::factory(),
            'tax_class_id' => TaxClass::factory(),
            'name_en' => fake()->unique()->words(3, true),
            'name_ar' => 'منتج '.fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
