<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxClass>
 */
class TaxClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name_en' => fake()->unique()->words(2, true).' Tax',
            'name_ar' => 'ضريبة '.fake()->unique()->word(),
            'rate' => fake()->randomElement([0, 5, 14, 15]),
        ];
    }
}
