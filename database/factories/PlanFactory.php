<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name_en' => ucfirst($name),
            'name_ar' => ucfirst($name),
            'slug' => Str::slug($name),
            'max_stores' => fake()->numberBetween(1, 10),
            'max_users' => fake()->numberBetween(5, 100),
            'price' => fake()->randomFloat(2, 0, 500),
            'duration_days' => fake()->randomElement([14, 30, 90, 365]),
            'is_active' => true,
        ];
    }

    /**
     * Trial plan state.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'name_en' => 'Trial',
            'name_ar' => 'تجربة',
            'slug' => 'trial',
            'max_stores' => 1,
            'max_users' => 5,
            'price' => 0.00,
            'duration_days' => 14,
        ]);
    }
}
