<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = fake()->unique()->words(2, true);
        $nameAr = 'فئة '.fake()->unique()->word();

        return [
            'company_id' => Company::factory(),
            'name_en' => ucfirst($nameEn),
            'name_ar' => $nameAr,
            'is_active' => true,
        ];
    }
}
