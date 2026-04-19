<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name_en' => fake()->company().' Branch',
            'name_ar' => fake()->company().' فرع',
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'working_hours' => [
                ['day' => 'saturday', 'from' => '08:00', 'to' => '22:00'],
                ['day' => 'sunday', 'from' => '08:00', 'to' => '22:00'],
                ['day' => 'monday', 'from' => '08:00', 'to' => '22:00'],
                ['day' => 'tuesday', 'from' => '08:00', 'to' => '22:00'],
                ['day' => 'wednesday', 'from' => '08:00', 'to' => '22:00'],
                ['day' => 'thursday', 'from' => '08:00', 'to' => '22:00'],
                ['day' => 'friday', 'from' => '10:00', 'to' => '22:00'],
            ],
            'is_active' => true,
        ];
    }
}
