<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Store;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $units = ['Piece', 'Kilogram', 'Gram', 'Liter', 'Milliliter', 'Box', 'Pack', 'Carton'];
        $abbreviations = ['pc', 'kg', 'g', 'L', 'mL', 'box', 'pk', 'ctn'];
        $index = array_rand($units);

        return [
            'company_id' => Company::factory(),
            'store_id' => Store::factory(),
            'name_en' => $units[$index],
            'name_ar' => 'وحدة '.fake()->unique()->word(),
            'abbreviation_en' => $abbreviations[$index],
            'abbreviation_ar' => $abbreviations[$index],
        ];
    }
}
