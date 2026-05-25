<?php

namespace Tests\Feature\Filament;

use App\Enums\DiscountType;
use App\Enums\PriceType;
use App\Filament\Resources\SaleInvoices\Pages\CreateSaleInvoice;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class SaleInvoiceDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected Store $store;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);

        $this->company = Company::factory()->create();
        $this->store = Store::factory()->create(['company_id' => $this->company->id]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $product = Product::factory()->create([
            'store_id' => $this->store->id,
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'retail_price' => 100.00,
            'retail_is_price_negotiable' => true,
            'min_retail_price' => 80.00, // max 20 discount
        ]);
    }

    public function test_it_can_apply_item_level_fixed_discount()
    {
        Livewire::actingAs($this->user)
            ->test(CreateSaleInvoice::class)
            ->fillForm([
                'store_id' => $this->store->id,
                'payment_method' => 'cash',
                'subtotal_amount' => 200.00,
            ])
            ->set('data.items', [
                [
                    'product_variant_id' => $this->variant->id,
                    'price_type' => PriceType::Retail->value,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'discount_type' => DiscountType::Fixed->value,
                    'unit_discount_amount' => 10.00,
                    'line_total_discount' => 20.00,
                    'subtotal' => 200.00,
                    'line_total' => 180.00,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sale_invoice_items', [
            'product_variant_id' => $this->variant->id,
            'unit_discount_amount' => 10.00,
            'line_total_discount' => 20.00, // 10 * 2
            'line_total' => 180.00, // (100 - 10) * 2
        ]);

        $this->assertDatabaseHas('sale_invoices', [
            'grand_total_discount' => 20.00,
            'total_amount' => 180.00,
        ]);
    }

    public function test_it_prevents_item_discount_below_minimum_price()
    {
        Livewire::actingAs($this->user)
            ->test(CreateSaleInvoice::class)
            ->fillForm([
                'store_id' => $this->store->id,
                'payment_method' => 'cash',
                'subtotal_amount' => 100.00,
            ])
            ->set('data.items', [
                [
                    'product_variant_id' => $this->variant->id,
                    'price_type' => PriceType::Retail->value,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'discount_type' => DiscountType::Fixed->value,
                    'unit_discount_amount' => 30.00, // Min retail price is 80, this would make it 70
                    'line_total_discount' => 30.00,
                    'subtotal' => 100.00,
                    'line_total' => 70.00,
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['items.0.unit_discount_amount']);
    }

    public function test_it_can_apply_global_invoice_discount()
    {
        $res = Livewire::actingAs($this->user)
            ->test(CreateSaleInvoice::class)
            ->fillForm([
                'store_id' => $this->store->id,
                'payment_method' => 'cash',
                'discount_type' => DiscountType::Fixed->value,
                'subtotal_amount' => 100.00,
                'total_amount' => 85.00,
            ])
            ->set('data.discount_amount', 15.00)
            ->set('data.items', [
                [
                    'product_variant_id' => $this->variant->id,
                    'price_type' => PriceType::Retail->value,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'line_total_discount' => 0.00,
                    'subtotal' => 100.00,
                    'line_total' => 100.00,
                ],
            ])
            ->call('create');
        $res->assertHasNoFormErrors();

        $this->assertDatabaseHas('sale_invoices', [
            'global_discount_amount' => 15.00,
            'grand_total_discount' => 15.00,
            'total_amount' => 85.00,
        ]);
    }

    public function test_it_prevents_global_discount_causing_items_to_drop_below_minimum()
    {
        Livewire::actingAs($this->user)
            ->test(CreateSaleInvoice::class)
            ->fillForm([
                'store_id' => $this->store->id,
                'payment_method' => 'cash',
                'discount_type' => DiscountType::Fixed->value,
                'subtotal_amount' => 100.00,
                'total_amount' => 75.00,
            ])
            ->set('data.discount_amount', 25.00)
            ->set('data.items', [
                [
                    'product_variant_id' => $this->variant->id,
                    'price_type' => PriceType::Retail->value,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'line_total_discount' => 0.00,
                    'subtotal' => 100.00,
                    'line_total' => 100.00,
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['discount_amount']);
    }
}
