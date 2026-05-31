<?php

namespace Database\Factories;

use App\Enums\SaleReturnStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SaleInvoice;
use App\Models\SaleReturnInvoice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnInvoice>
 */
class SaleReturnInvoiceFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'original_invoice_id' => SaleInvoice::factory(),
            'return_number' => 'SR-'.date('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => SaleReturnStatus::Draft,
            'return_reason' => $this->faker->sentence(),
            'items_refund_total' => 0,
            'extra_items_total' => 0,
            'total_refund_amount' => 0,
            'notes' => $this->faker->optional()->paragraph(),
            'returned_at' => now()->toDateString(),
            'created_by' => User::factory(),
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SaleReturnStatus::Finalized,
            'finalized_at' => now(),
            'finalized_by' => User::factory(),
        ]);
    }
}
