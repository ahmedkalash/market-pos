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
                'Saturday' => '08:00-22:00',
                'Sunday' => '08:00-22:00',
                'Monday' => '08:00-22:00',
                'Tuesday' => '08:00-22:00',
                'Wednesday' => '08:00-22:00',
                'Thursday' => '08:00-22:00',
                'Friday' => '10:00-22:00',
            ],
            'is_active' => true,
        ];
    }
}
