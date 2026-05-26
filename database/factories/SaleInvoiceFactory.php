<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SaleInvoiceReturnStatus;
use App\Enums\SaleInvoiceStatus;
use App\Models\Company;
use App\Models\SaleInvoice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleInvoice>
 */
class SaleInvoiceFactory extends Factory
{
    protected $model = SaleInvoice::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'store_id' => Store::factory(),
            'invoice_number' => 'SI-'.fake()->unique()->numerify('######'),
            'status' => SaleInvoiceStatus::Draft,
            'return_status' => SaleInvoiceReturnStatus::None,
            'payment_method' => null,
            'subtotal' => 0.00,
            'total_before_tax' => 0.00,
            'total_tax_amount' => 0.00,
            'total_amount' => 0.00,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SaleInvoiceStatus::Finalized,
            'payment_method' => PaymentMethod::Cash,
            'finalized_at' => now(),
            'finalized_by' => User::factory(),
        ]);
    }
}
