<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PriceType;
use App\Enums\SaleInvoiceStatus;
use App\Filament\Pages\PosTerminal;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\ShippingDestination;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\PosCheckoutService;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_checkout_with_dual_percentage_discounts_calculates_properly(): void
    {
        $this->actingAs($this->user);

        // Variant price is $20.00. Qty is 2. Subtotal = 40.00.
        // Item discount 10% -> 2.00 unit discount * 2 = 4.00 total discount.
        // Subtotal after item discount = 36.00.
        // Global discount 10% -> 36.00 * 10% = 3.60.
        // Final total amount = 36.00 - 3.60 = 32.40.
        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 2,
                'discount_type' => 'percentage',
                'discount_amount' => 10,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount_type' => 'percentage',
                'global_discount_amount' => 10,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(40.00, (float) $invoice->subtotal);
        $this->assertEquals(3.60, (float) $invoice->global_discount_amount);
        $this->assertEquals(7.60, (float) $invoice->grand_total_discount);
        $this->assertEquals(32.40, (float) $invoice->total_amount);
    }

    public function test_checkout_with_extra_items_addition_and_subtraction_calculates_properly(): void
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
                'shipping_cost' => 5.00,
                'extra_items' => [
                    [
                        'name' => 'Gift Box Wrapping',
                        'action_type' => 'addition',
                        'amount' => 15.00,
                    ],
                    [
                        'name' => 'Promotion Rebate',
                        'action_type' => 'subtraction',
                        'amount' => 5.00,
                    ],
                ],
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(10.00, (float) $invoice->extra_items_total);
        $this->assertEquals(55.00, (float) $invoice->total_amount);
        $this->assertCount(2, $invoice->extraItems);
    }

    public function test_checkout_with_wholesale_price_type_and_threshold_applies_correct_pricing(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $uom = UnitOfMeasure::where('company_id', $this->company->id)->first();

        $wholesaleVariant = ProductVariant::factory()->withStock(50)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 25.00,
            'wholesale_price' => 18.00,
            'wholesale_is_price_negotiable' => true,
            'min_wholesale_price' => 15.00,
            'wholesale_qty_threshold' => 5,
        ]);

        $cart = [
            [
                'variant_id' => $wholesaleVariant->id,
                'name' => $wholesaleVariant->full_qualified_name,
                'price_type' => 'wholesale',
                'price' => 18.00,
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

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(90.00, (float) $invoice->total_amount);
        $this->assertEquals(PriceType::Wholesale, $invoice->items->first()->price_type);
        $this->assertEquals(18.00, (float) $invoice->items->first()->unit_price);
    }

    public function test_checkout_fails_when_item_discount_exceeds_minimum_allowed_price(): void
    {
        $this->actingAs($this->user);

        // Variant price is 20.00, min is 10.00.
        // Discount of 15.00 pushes price to 5.00, which is below the minimum allowed of 10.00.
        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 1,
                'discount_type' => 'fixed',
                'discount_amount' => 15.00,
            ],
        ];

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ]);

        $this->assertDatabaseMissing('sale_invoices', [
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_checkout_fails_when_global_discount_exceeds_minimum_allowed_total(): void
    {
        $this->actingAs($this->user);

        // Variant price is 20.00, min is 10.00. Qty is 2.
        // Subtotal = 40.00. Minimum allowed total = 20.00.
        // Global fixed discount of 25.00 pushes total to 15.00, which breaches minimum allowed total of 20.00.
        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 2,
                'discount' => 0,
            ],
        ];

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 25.00,
                'shipping_cost' => 0,
            ]);

        $this->assertDatabaseMissing('sale_invoices', [
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_can_create_shipping_destination_inline(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);

        $result = $component->instance()->createShippingDestination([
            'name' => 'Nasr City - Zone B',
            'cost' => 65.50,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('Nasr City - Zone B', $result['name']);
        $this->assertEquals(65.50, $result['cost']);

        $this->assertDatabaseHas('shipping_destinations', [
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'name' => 'Nasr City - Zone B',
            'cost' => 65.50,
            'is_active' => true,
        ]);
    }

    public function test_checkout_with_shipping_destination_and_custom_cost(): void
    {
        $this->actingAs($this->user);

        $destination = ShippingDestination::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'name' => 'Downtown Express',
            'cost' => 40.00,
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

        // Cashier chose destination (default 40.00) but customized shipping cost to 55.00 and added custom address
        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'shipping_destination_id' => $destination->id,
                'shipping_cost' => 55.00,
                'shipping_address' => 'Building 12, Tahrir St.',
            ])
            ->assertDispatched('checkout-successful');

        $this->assertDatabaseHas('sale_invoices', [
            'store_id' => $this->store->id,
            'shipping_destination_id' => $destination->id,
            'shipping_cost' => 55.00,
            'shipping_address' => 'Building 12, Tahrir St.',
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_checkout_with_shipping_destination_zero_cost_free_shipping(): void
    {
        $this->actingAs($this->user);

        $destination = ShippingDestination::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'name' => 'Free Delivery Zone',
            'cost' => 0.00,
        ]);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 50.00,
                'qty' => 2,
                'discount' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'shipping_destination_id' => $destination->id,
                'shipping_cost' => 0.00,
                'shipping_address' => 'Local Neighborhood',
            ])
            ->assertDispatched('checkout-successful');

        $this->assertDatabaseHas('sale_invoices', [
            'store_id' => $this->store->id,
            'shipping_destination_id' => $destination->id,
            'shipping_cost' => 0.00,
            'shipping_address' => 'Local Neighborhood',
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_cashier_can_create_customer_inline_from_pos(): void
    {
        $this->actingAs($this->user);

        $customerData = [
            'name' => 'Sara Connor',
            'phone' => '01012345678',
            'email' => 'sara@example.com',
            'address' => '42 Elm St.',
        ];

        $component = Livewire::test(PosTerminal::class);
        $result = $component->instance()->createCustomer($customerData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('Sara Connor', $result['name']);
        $this->assertEquals('01012345678', $result['phone']);
        $this->assertEquals('sara@example.com', $result['email']);
        $this->assertEquals('42 Elm St.', $result['address']);

        $this->assertDatabaseHas('customers', [
            'id' => $result['id'],
            'company_id' => $this->company->id,
            'name' => 'Sara Connor',
            'phone' => '01012345678',
            'email' => 'sara@example.com',
            'address' => '42 Elm St.',
            'is_active' => true,
        ]);
    }

    public function test_inline_customer_creation_fails_without_required_name(): void
    {
        $this->actingAs($this->user);

        $this->expectException(ValidationException::class);

        $component = Livewire::test(PosTerminal::class);
        $component->instance()->createCustomer([
            'name' => '',
            'phone' => '01012345678',
        ]);
    }

    public function test_wholesale_checkout_rejected_when_quantity_is_below_wholesale_threshold(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $uom = UnitOfMeasure::where('company_id', $this->company->id)->first();

        $wholesaleVariant = ProductVariant::factory()->withStock(50)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 25.00,
            'wholesale_price' => 18.00,
            'wholesale_enabled' => true,
            'wholesale_is_price_negotiable' => true,
            'min_wholesale_price' => 15.00,
            'wholesale_qty_threshold' => 10,
        ]);

        $cart = [
            [
                'variant_id' => $wholesaleVariant->id,
                'name' => $wholesaleVariant->full_qualified_name,
                'price_type' => 'wholesale',
                'price' => 18.00,
                'qty' => 5, // Below threshold of 10
                'discount' => 0,
            ],
        ];

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ]);

        $this->assertDatabaseMissing('sale_invoices', [
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_wholesale_checkout_service_throws_validation_exception_when_below_threshold(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $uom = UnitOfMeasure::where('company_id', $this->company->id)->first();

        $wholesaleVariant = ProductVariant::factory()->withStock(50)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 25.00,
            'wholesale_price' => 18.00,
            'wholesale_enabled' => true,
            'wholesale_is_price_negotiable' => true,
            'min_wholesale_price' => 15.00,
            'wholesale_qty_threshold' => 10,
        ]);

        $cart = [
            [
                'variant_id' => $wholesaleVariant->id,
                'price_type' => 'wholesale',
                'qty' => 4,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('pos.wholesale_min_qty_error', ['product' => $wholesaleVariant->full_qualified_name, 'min' => 10]));

        PosCheckoutService::make()->checkout($cart, [
            'store_id' => $this->store->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_wholesale_checkout_succeeds_when_quantity_meets_or_exceeds_threshold(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $uom = UnitOfMeasure::where('company_id', $this->company->id)->first();

        $wholesaleVariant = ProductVariant::factory()->withStock(50)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 25.00,
            'wholesale_price' => 18.00,
            'wholesale_enabled' => true,
            'wholesale_is_price_negotiable' => true,
            'min_wholesale_price' => 15.00,
            'wholesale_qty_threshold' => 10,
        ]);

        $cart = [
            [
                'variant_id' => $wholesaleVariant->id,
                'name' => $wholesaleVariant->full_qualified_name,
                'price_type' => 'wholesale',
                'price' => 18.00,
                'qty' => 12, // Meets and exceeds threshold of 10
                'discount' => 0,
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
        $this->assertEquals(216.00, (float) $invoice->total_amount); // 12 * 18.00
        $this->assertEquals(PriceType::Wholesale, $invoice->items->first()->price_type);
        $this->assertEquals(18.00, (float) $invoice->items->first()->unit_price);
        $this->assertEquals(12, $invoice->items->first()->quantity);
    }

    public function test_checkout_rejected_when_requested_quantity_exceeds_available_stock(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $uom = UnitOfMeasure::where('company_id', $this->company->id)->first();

        $lowStockVariant = ProductVariant::factory()->withStock(3)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 30.00,
        ]);

        $cart = [
            [
                'variant_id' => $lowStockVariant->id,
                'name' => $lowStockVariant->full_qualified_name,
                'price' => 30.00,
                'qty' => 5, // Exceeds available stock of 3
                'discount' => 0,
            ],
        ];

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ]);

        $this->assertDatabaseMissing('sale_invoices', [
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_checkout_service_throws_validation_exception_when_exceeding_stock(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $uom = UnitOfMeasure::where('company_id', $this->company->id)->first();

        $lowStockVariant = ProductVariant::factory()->withStock(2)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 30.00,
        ]);

        $cart = [
            [
                'variant_id' => $lowStockVariant->id,
                'qty' => 5,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('pos.insufficient_stock_error', ['product' => $lowStockVariant->full_qualified_name, 'available' => 2]));

        PosCheckoutService::make()->checkout($cart, [
            'store_id' => $this->store->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_checkout_rejected_when_item_price_is_non_negotiable_and_discount_is_applied(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $uom = UnitOfMeasure::where('company_id', $this->company->id)->first();

        $nonNegotiableVariant = ProductVariant::factory()->withStock(20)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 50.00,
            'retail_is_price_negotiable' => false,
        ]);

        $cart = [
            [
                'variant_id' => $nonNegotiableVariant->id,
                'name' => $nonNegotiableVariant->full_qualified_name,
                'price' => 50.00,
                'qty' => 1,
                'discount_type' => 'fixed',
                'discount_amount' => 5.00, // Not allowed because price is not negotiable
            ],
        ];

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'global_discount' => 0,
                'shipping_cost' => 0,
            ]);

        $this->assertDatabaseMissing('sale_invoices', [
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_checkout_with_new_inline_created_customer_associates_invoice(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);
        $customer = $component->instance()->createCustomer([
            'name' => 'John Wick',
            'phone' => '01099998888',
            'email' => 'john@continental.com',
            'address' => 'Continental Hotel NY',
        ]);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price' => 20.00,
                'qty' => 2,
                'discount' => 0,
            ],
        ];

        $component->call('processCheckout', $cart, [
            'customer_id' => $customer['id'],
            'global_discount' => 0,
            'shipping_cost' => 0,
        ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($customer['id'], $invoice->customer_id);
        $this->assertEquals(40.00, (float) $invoice->total_amount);
    }
}
