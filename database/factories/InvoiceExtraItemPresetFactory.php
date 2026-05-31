<?php

namespace Database\Factories;

use App\Enums\ExtraItemActionType;
use App\Models\Company;
use App\Models\InvoiceExtraItemPreset;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceExtraItemPreset>
 */
class InvoiceExtraItemPresetFactory extends Factory
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
            'store_id' => Store::factory(),
            'name' => $this->faker->words(3, true),
            'action_type' => $this->faker->randomElement([ExtraItemActionType::Addition, ExtraItemActionType::Subtraction]),
            'amount' => $this->faker->randomFloat(2, 5, 50),
            'is_refundable' => false,
            'invoice_type' => $this->faker->randomElement(['sale_invoice', 'sale_return', 'purchase_invoice', 'purchase_return']),
            'notes' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
