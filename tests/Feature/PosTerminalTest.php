<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\SaleInvoiceStatus;
use App\Filament\Pages\PosTerminal;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTerminalTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Store $store;

    protected User $user;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->store = Store::factory()->create(['company_id' => $this->company->id]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $category = ProductCategory::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $taxClass = TaxClass::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'tax_class_id' => $taxClass->id,
        ]);

        $uom = UnitOfMeasure::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $this->variant = ProductVariant::factory()->withStock(50)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 20.00,
            'retail_is_price_negotiable' => true,
            'min_retail_price' => 10.00,
        ]);
    }

    public function test_pos_terminal_page_renders_successfully(): void
    {
        $this->actingAs($this->user);

        Livewire::test(PosTerminal::class)
            ->assertSuccessful();
    }

    public function test_cashier_can_add_products_and_checkout_successfully(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 2,
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful')
            ->assertNotified();

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(SaleInvoiceStatus::Finalized, $invoice->status);
        $this->assertEquals(40.00, (float) $invoice->total_amount);
    }

    public function test_checkout_deducts_stock_correctly(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 5,
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $this->variant->refresh();
        $this->assertEquals(45.0, (float) $this->variant->quantity);

        $movement = InventoryMovement::where([
            'reference_type' => SaleInvoice::class,
            'type' => MovementType::Sale,
        ])->latest()->first();

        $this->assertNotNull($movement);
        $this->assertEquals(5.0, (float) $movement->quantity);
    }

    public function test_checkout_applies_global_discount_correctly(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 2, // 40.00 subtotal
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 5.00,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(35.00, (float) $invoice->total_amount);
        $this->assertEquals(5.00, (float) $invoice->discount_amount);
    }

    public function test_checkout_applies_shipping_cost_correctly(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 1,
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 10.00,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(10.00, (float) $invoice->shipping_cost);
        $this->assertEquals(30.00, (float) $invoice->total_amount);
    }

    public function test_checkout_fails_on_insufficient_stock(): void
    {
        $this->actingAs($this->user);

        // Variant only has 50 stock, try to buy 100
        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 100,
                'discount' => 0,
            ],
        ];

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ]);

        // Verify stock was not touched
        $this->variant->refresh();
        $this->assertEquals(50.0, (float) $this->variant->quantity);
    }

    public function test_hold_cart_saves_draft_invoice(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 2,
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(SaleInvoiceStatus::Draft, $invoice->status);

        // Verify stock is NOT deducted on draft hold
        $this->variant->refresh();
        $this->assertEquals(50.0, (float) $this->variant->quantity);
    }

    public function test_pos_page_is_not_accessible_without_authentication(): void
    {
        $this->get(PosTerminal::getUrl())
            ->assertRedirect();
    }

    public function test_company_level_user_can_render_pos_and_checkout_with_store_fallback(): void
    {
        /** @var User $companyAdmin */
        $companyAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $this->actingAs($companyAdmin);

        Livewire::test(PosTerminal::class)
            ->assertSuccessful();

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 1,
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($this->store->id, $invoice->store_id);
    }

    public function test_checkout_with_selected_customer_associates_customer_id(): void
    {
        $this->actingAs($this->user);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 1,
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'customer_id' => $customer->id,
                'global_discount' => 0,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($customer->id, $invoice->customer_id);
    }

    public function test_checkout_with_item_level_discount_calculates_properly(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 2,
                'discount' => 2.00, // $2.00 unit discount * 2 = $4.00 total discount => $36.00
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(36.00, (float) $invoice->total_amount);
    }

    public function test_checkout_with_multiple_variants_deducts_all_stocks_and_creates_items(): void
    {
        $this->actingAs($this->user);

        $product2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $uom = UnitOfMeasure::where('company_id', $this->company->id)->first();

        $variant2 = ProductVariant::factory()->withStock(30)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product2->id,
            'uom_id' => $uom->id,
            'retail_price' => 15.00,
        ]);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 2,
                'discount' => 0,
            ],
            [
                'variant_id' => $variant2->id,
                'name' => $variant2->full_qualified_name,
                'price' => 15.00,
                'qty' => 3,
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 5.00,
                'shipping_cost' => 10.00,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(90.00, (float) $invoice->total_amount);
        $this->assertCount(2, $invoice->items);

        $this->variant->refresh();
        $variant2->refresh();
        $this->assertEquals(48.0, (float) $this->variant->quantity);
        $this->assertEquals(27.0, (float) $variant2->quantity);
    }
}
