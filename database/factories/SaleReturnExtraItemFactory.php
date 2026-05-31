<?php

namespace Database\Factories;

use App\Enums\ExtraItemActionType;
use App\Models\SaleReturnExtraItem;
use App\Models\SaleReturnInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnExtraItem>
 */
class SaleReturnExtraItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_return_invoice_id' => SaleReturnInvoice::factory(),
            'name' => $this->faker->words(3, true),
            'action_type' => $this->faker->randomElement([ExtraItemActionType::Addition, ExtraItemActionType::Subtraction]),
            'amount' => $this->faker->randomFloat(2, 5, 50),
            'is_refundable' => false,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
