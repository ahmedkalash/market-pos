<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'plan_id' => Plan::factory(),
            'name_en' => $name,
            'name_ar' => $name,
            'slug' => Str::slug($name),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'vat_number' => fake()->numerify('###-###-###'),
            'vat_rate' => 14.00,
            'currency' => 'EGP',
            'locale' => 'ar',
            'receipt_header' => null,
            'receipt_footer' => null,
            'receipt_show_logo' => true,
            'is_active' => true,
        ];
    }

    /**
     * Company with an active subscription.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Company with an expired subscription.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [

        ]);
    }

    /**
     * Suspended company.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
