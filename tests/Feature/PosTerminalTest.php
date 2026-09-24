<?php

namespace Tests\Feature;

use App\DTOs\Checkout\CartItemDTO;
use App\DTOs\Checkout\CheckoutMetaDataDTO;
use App\DTOs\Checkout\ExtraItemDTO;
use App\Enums\DiscountType;
use App\Enums\ExtraItemActionType;
use App\Enums\InvoiceType;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\SaleInvoiceStatus;
use App\Filament\Pages\PosTerminal;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\InvoiceExtraItemPreset;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\ShippingDestination;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\PosCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 5,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 2, // 40.00 subtotal
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_type' => 'fixed',
                'global_discount_amount' => 5.00,
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
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 100,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');

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
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
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

    public function test_company_level_user_cannot_checkout_without_selecting_store(): void
    {
        /** @var User $companyAdmin */
        $companyAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $this->actingAs($companyAdmin);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');

        $this->assertDatabaseMissing('sale_invoices', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_company_level_user_can_switch_store_and_checkout_successfully(): void
    {
        /** @var User $companyAdmin */
        $companyAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $this->actingAs($companyAdmin);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('changeStore', $this->store->id)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('company_id', $this->company->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($this->store->id, $invoice->store_id);
    }

    public function test_company_level_user_cannot_hold_cart_without_selecting_store(): void
    {
        /** @var User $companyAdmin */
        $companyAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $this->actingAs($companyAdmin);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('cart-held-successful');

        $this->assertDatabaseMissing('sale_invoices', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_company_level_user_cannot_create_shipping_destination_without_selecting_store(): void
    {
        /** @var User $companyAdmin */
        $companyAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $this->actingAs($companyAdmin);

        $component = Livewire::test(PosTerminal::class);
        $result = $component->instance()->createShippingDestination([
            'name' => 'Downtown Express',
            'cost' => 15.00,
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertSame(__('pos.select_store_first'), $result['message']);
        $this->assertArrayHasKey('store_id', $result['errors']);
        $this->assertContains(__('pos.select_store_first'), $result['errors']['store_id']);
    }

    public function test_create_shipping_destination_fails_with_multiple_validation_errors(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);
        $result = $component->instance()->createShippingDestination([
            'name' => '',
            'cost' => -10.00,
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('cost', $result['errors']);
        $this->assertContains(__('pos.destination_name_required'), $result['errors']['name']);
        $this->assertContains(__('pos.cost_must_be_positive'), $result['errors']['cost']);
    }

    public function test_create_shipping_destination_fails_with_non_numeric_cost(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);
        $result = $component->instance()->createShippingDestination([
            'name' => 'Alexandria Port',
            'cost' => 'invalid-abc',
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertArrayHasKey('cost', $result['errors']);
        $this->assertContains(__('pos.cost_must_be_number'), $result['errors']['cost']);
    }

    public function test_create_shipping_destination_fails_with_missing_cost(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);
        $result = $component->instance()->createShippingDestination([
            'name' => 'Alexandria Port',
            'cost' => null,
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertArrayHasKey('cost', $result['errors']);
        $this->assertContains(__('pos.cost_required'), $result['errors']['cost']);
    }

    public function test_create_shipping_destination_assigns_active_pos_store_id(): void
    {
        /** @var User $companyAdmin */
        $companyAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $storeB = Store::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($companyAdmin);

        $component = Livewire::test(PosTerminal::class)
            ->call('changeStore', $storeB->id);

        $result = $component->instance()->createShippingDestination([
            'name' => 'Store B Delivery',
            'cost' => 25.00,
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('shipping_destinations', [
            'id' => $result['data']['id'],
            'company_id' => $this->company->id,
            'store_id' => $storeB->id,
            'name' => 'Store B Delivery',
        ]);
    }

    public function test_shipping_destinations_and_presets_are_isolated_by_store_for_company_level_user(): void
    {
        /** @var User $companyAdmin */
        $companyAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $storeA = $this->store;
        $storeB = Store::factory()->create(['company_id' => $this->company->id]);

        $destA = ShippingDestination::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $storeA->id,
            'name' => 'Destination Store A',
        ]);
        $destB = ShippingDestination::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $storeB->id,
            'name' => 'Destination Store B',
        ]);

        $presetA = InvoiceExtraItemPreset::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $storeA->id,
            'invoice_type' => InvoiceType::SaleInvoice,
            'name' => 'Preset Store A',
        ]);
        $presetB = InvoiceExtraItemPreset::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $storeB->id,
            'invoice_type' => InvoiceType::SaleInvoice,
            'name' => 'Preset Store B',
        ]);

        $this->actingAs($companyAdmin);

        // Initial state: no store selected
        $component = Livewire::test(PosTerminal::class);
        $this->assertEmpty($component->get('shippingDestinationList'));
        $this->assertEmpty($component->instance()->getExtraItemPresets());

        // Select Store A
        $component->call('changeStore', $storeA->id);
        $destIdsA = collect($component->get('shippingDestinationList'))->pluck('id')->all();
        $presetIdsA = collect($component->instance()->getExtraItemPresets())->pluck('id')->all();
        $this->assertContains($destA->id, $destIdsA);
        $this->assertNotContains($destB->id, $destIdsA);
        $this->assertContains($presetA->id, $presetIdsA);
        $this->assertNotContains($presetB->id, $presetIdsA);

        // Switch to Store B
        $component->call('changeStore', $storeB->id);
        $destIdsB = collect($component->get('shippingDestinationList'))->pluck('id')->all();
        $presetIdsB = collect($component->instance()->getExtraItemPresets())->pluck('id')->all();
        $this->assertContains($destB->id, $destIdsB);
        $this->assertNotContains($destA->id, $destIdsB);
        $this->assertContains($presetB->id, $presetIdsB);
        $this->assertNotContains($presetA->id, $presetIdsB);
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
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 2,
                'discount_type' => 'fixed',
                'discount_amount' => 2.00, // $2.00 unit discount * 2 = $4.00 total discount => $36.00
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 2,
            ],
            [
                'variant_id' => $variant2->id,
                'name' => $variant2->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 3,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_type' => 'fixed',
                'global_discount_amount' => 5.00,
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
                'price_type' => 'retail',
                'qty' => 2,
                'discount_type' => 'percentage',
                'discount_amount' => 10,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'qty' => 5,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 1,
                'discount_type' => 'fixed',
                'discount_amount' => 15.00,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');

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
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_type' => 'fixed',
                'global_discount_amount' => 25.00,
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');

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
        $this->assertTrue($result['success']);
        $this->assertEquals('Nasr City - Zone B', $result['data']['name']);
        $this->assertEquals(65.50, $result['data']['cost']);
        $this->assertNull($result['message']);
        $component->assertNotified(__('pos.destination_created_successfully'));

        $this->assertDatabaseHas('shipping_destinations', [
            'id' => $result['data']['id'],
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
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        // Cashier chose destination (default 40.00) but customized shipping cost to 55.00 and added custom address
        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
        $this->assertTrue($result['success']);
        $this->assertNull($result['message']);
        $component->assertNotified(__('pos.customer_created_successfully'));
        $this->assertArrayHasKey('id', $result['data']);
        $this->assertEquals('Sara Connor', $result['data']['name']);
        $this->assertEquals('01012345678', $result['data']['phone']);
        $this->assertEquals('sara@example.com', $result['data']['email']);
        $this->assertEquals('42 Elm St.', $result['data']['address']);

        $this->assertDatabaseHas('customers', [
            'id' => $result['data']['id'],
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

        $component = Livewire::test(PosTerminal::class);
        $result = $component->instance()->createCustomer([
            'name' => '',
            'phone' => '01012345678',
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertArrayHasKey('name', $result['errors']);
    }

    public function test_inline_customer_creation_fails_with_multiple_validation_errors(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);
        $result = $component->instance()->createCustomer([
            'name' => '',
            'email' => 'invalid-email-address',
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
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
                'qty' => 5, // Below threshold of 10
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');

        $this->assertDatabaseMissing('sale_invoices', [
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_wholesale_checkout_service_throws_exception_when_below_threshold(): void
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
                'price_type' => 'wholesale',
                'qty' => 4,
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('sale_invoice.wholesale_min_qty_breached', [
            'item' => $wholesaleVariant->name(),
            'min' => 10,
        ]));

        PosCheckoutService::make()->checkout($cart, [
            'store_id' => $this->store->id,
            'company_id' => $this->company->id,
            'payment_method' => 'cash',
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
                'qty' => 12, // Meets and exceeds threshold of 10
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 5, // Exceeds available stock of 3
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');

        $this->assertDatabaseMissing('sale_invoices', [
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);
    }

    public function test_checkout_service_throws_validation_exception_when_exceeding_stock(): void
    {
        $this->actingAs($this->user);

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
                'price_type' => 'retail',
                'qty' => 5,
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('pos.insufficient_stock_error', ['product' => $lowStockVariant->full_qualified_name, 'available' => 2]));

        PosCheckoutService::make()->checkout($cart, [
            'store_id' => $this->store->id,
            'company_id' => $this->company->id,
            'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 1,
                'discount_type' => 'fixed',
                'discount_amount' => 5.00, // Not allowed because price is not negotiable
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');

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

        $this->assertTrue($customer['success']);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        $component->call('processCheckout', $cart, [
            'customer_id' => $customer['data']['id'],
            'payment_method' => 'cash',
            'shipping_cost' => 0,
        ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($customer['data']['id'], $invoice->customer_id);
        $this->assertEquals(40.00, (float) $invoice->total_amount);
    }

    public function test_company_level_user_cannot_switch_to_non_existent_or_other_company_store(): void
    {
        /** @var User $companyAdmin */
        $companyAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $otherCompany = Company::factory()->create();
        $otherStore = Store::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($companyAdmin);

        $component = Livewire::test(PosTerminal::class)
            ->call('changeStore', 999999)
            ->assertNotified(__('pos.store_not_found'));

        $this->assertNull($component->get('storeId'));

        $component->call('changeStore', $otherStore->id)
            ->assertNotified(__('pos.store_not_found'));

        $this->assertNull($component->get('storeId'));
    }

    public function test_search_by_exact_barcode_returns_matching_variant_via_fast_path(): void
    {
        $this->actingAs($this->user);

        $variant1 = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Cola Can',
        ]);
        ProductBarcode::create([
            'product_variant_id' => $variant1->id,
            'barcode' => '6281001234567',
        ]);

        $variant2 = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Orange Juice',
        ]);
        ProductBarcode::create([
            'product_variant_id' => $variant2->id,
            'barcode' => '6281007654321',
        ]);

        $component = Livewire::test(PosTerminal::class)
            ->set('search', '6281001234567');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertContains($variant1->id, $productIds);
        $this->assertNotContains($variant2->id, $productIds);
        $this->assertNotContains($this->variant->id, $productIds);
    }

    public function test_search_by_barcode_prefix_returns_all_matching_variants(): void
    {
        $this->actingAs($this->user);

        $variant1 = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Milk 1L',
        ]);
        ProductBarcode::create([
            'product_variant_id' => $variant1->id,
            'barcode' => '888111222',
        ]);

        $variant2 = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Milk 2L',
        ]);
        ProductBarcode::create([
            'product_variant_id' => $variant2->id,
            'barcode' => '888111333',
        ]);

        $variant3 = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Water 500ml',
        ]);
        ProductBarcode::create([
            'product_variant_id' => $variant3->id,
            'barcode' => '999555444',
        ]);

        $component = Livewire::test(PosTerminal::class)
            ->set('search', '888111');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertContains($variant1->id, $productIds);
        $this->assertContains($variant2->id, $productIds);
        $this->assertNotContains($variant3->id, $productIds);
    }

    public function test_search_by_product_name_falls_back_when_no_barcode_matches(): void
    {
        $this->actingAs($this->user);

        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Cappuccino',
        ]);
        ProductBarcode::create([
            'product_variant_id' => $variant->id,
            'barcode' => '1234987654',
        ]);

        // Search for "Cappuccino" (single token >= 3 chars, but not a barcode)
        $component = Livewire::test(PosTerminal::class)
            ->set('search', 'Cappuccino');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertContains($variant->id, $productIds);
    }

    public function test_search_by_multi_word_product_name_searches_by_name_directly(): void
    {
        $this->actingAs($this->user);

        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Green Herbal Tea',
        ]);

        $component = Livewire::test(PosTerminal::class)
            ->set('search', 'Herbal Tea');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertContains($variant->id, $productIds);
    }

    public function test_search_by_variant_name_en_and_ar_works_properly(): void
    {
        $this->actingAs($this->user);

        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'French Vanilla',
            'name_ar' => 'فانيليا فرنسية',
        ]);

        // Search English
        $componentEn = Livewire::test(PosTerminal::class)
            ->set('search', 'Vanilla');
        $productIdsEn = collect($componentEn->viewData('products')->items())->pluck('id')->all();
        $this->assertContains($variant->id, $productIdsEn);

        // Search Arabic
        $componentAr = Livewire::test(PosTerminal::class)
            ->set('search', 'فانيليا');
        $productIdsAr = collect($componentAr->viewData('products')->items())->pluck('id')->all();
        $this->assertContains($variant->id, $productIdsAr);
    }

    public function test_search_by_numeric_product_name_falls_back_when_no_barcode_exists(): void
    {
        $this->actingAs($this->user);

        // Variant with purely numeric name and no barcodes
        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => '10025',
        ]);

        $component = Livewire::test(PosTerminal::class)
            ->set('search', '10025');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertContains($variant->id, $productIds);
    }

    public function test_search_bypasses_barcode_fast_path_for_non_numeric_text(): void
    {
        $this->actingAs($this->user);

        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Spare Cable Type C',
        ]);

        $component = Livewire::test(PosTerminal::class)
            ->set('search', 'Cable');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertContains($variant->id, $productIds);
    }

    public function test_search_by_numeric_barcode_respects_configurable_min_barcode_search_length(): void
    {
        $this->actingAs($this->user);

        $variantWithBarcode = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Special Gizmo',
        ]);
        ProductBarcode::create([
            'product_variant_id' => $variantWithBarcode->id,
            'barcode' => '777123',
        ]);

        $variantWithName = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Model 777 Gizmo',
        ]);

        // When searching "777" (3 digits, which is < default minBarcodeSearchLength of 4):
        // It bypasses the barcode fast-path and searches names directly, finding "Model 777 Gizmo"
        $component = Livewire::test(PosTerminal::class)
            ->set('search', '777');

        $productIds = collect($component->viewData('products')->items())->pluck('id')->all();
        $this->assertContains($variantWithName->id, $productIds);
        $this->assertNotContains($variantWithBarcode->id, $productIds);

        // When configuring minBarcodeSearchLength to 3:
        // Searching "777" triggers the barcode fast-path, finding the variant with barcode "777123"
        $component->set('minBarcodeSearchLength', 3)
            ->set('search', '777');

        $productIds = collect($component->viewData('products')->items())->pluck('id')->all();
        $this->assertContains($variantWithBarcode->id, $productIds);
    }

    public function test_search_with_unmatched_term_returns_empty_results(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class)
            ->set('search', 'DEFINITELY_NON_EXISTENT_SEARCH_XYZ_9999');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertEmpty($productIds);
    }

    public function test_search_respects_store_isolation_even_if_barcode_matches_another_store(): void
    {
        $this->actingAs($this->user);

        $otherStore = Store::factory()->create(['company_id' => $this->company->id]);
        $otherProduct = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
        ]);
        $otherVariant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
            'product_id' => $otherProduct->id,
            'name_en' => 'Other Store Item',
        ]);
        ProductBarcode::create([
            'product_variant_id' => $otherVariant->id,
            'barcode' => '555666777888',
        ]);

        // Cashier in this->store searches for barcode belonging to otherStore
        $component = Livewire::test(PosTerminal::class)
            ->set('search', '555666777888');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertNotContains($otherVariant->id, $productIds);
        $this->assertEmpty($productIds);
    }

    public function test_search_with_short_term_searches_name_directly(): void
    {
        $this->actingAs($this->user);

        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'name_en' => 'Ice Cream',
        ]);

        // Search with 2 characters ('Ic' < 3 chars)
        $component = Livewire::test(PosTerminal::class)
            ->set('search', 'Ic');

        $products = $component->viewData('products');
        $productIds = collect($products->items())->pluck('id')->all();

        $this->assertContains($variant->id, $productIds);
    }

    public function test_pos_checkout_service_processes_dto_instances_directly(): void
    {
        $this->actingAs($this->user);

        $cartItems = [
            new CartItemDTO(
                variantId: $this->variant->id,
                quantity: 2.0,
                priceType: PriceType::Retail,
                unitPrice: 20.00
            ),
        ];

        $extraItem = new ExtraItemDTO(
            name: 'Eco Bag',
            actionType: ExtraItemActionType::Addition,
            amount: 2.00
        );

        $metaData = new CheckoutMetaDataDTO(
            storeId: $this->store->id,
            companyId: $this->company->id,
            paymentMethod: PaymentMethod::Cash,
            extraItems: [$extraItem]
        );

        $invoice = PosCheckoutService::make()->checkout($cartItems, $metaData);

        $this->assertNotNull($invoice);
        $this->assertEquals(SaleInvoiceStatus::Finalized, $invoice->status);
        $this->assertEquals(42.00, (float) $invoice->total_amount);
        $this->assertCount(1, $invoice->extraItems);
        $this->assertEquals('Eco Bag', $invoice->extraItems->first()->name);
    }

    public function test_pos_checkout_service_hold_cart_with_dto_instances_directly(): void
    {
        $this->actingAs($this->user);

        $cartItems = [
            new CartItemDTO(
                variantId: $this->variant->id,
                quantity: 3.0,
                priceType: PriceType::Retail,
                unitPrice: 20.00
            ),
        ];

        $metaData = new CheckoutMetaDataDTO(
            storeId: $this->store->id,
            companyId: $this->company->id,
            paymentMethod: PaymentMethod::Cash,
            globalDiscountType: DiscountType::Fixed,
            globalDiscountAmount: 5.00
        );

        $invoice = PosCheckoutService::make()->holdCart($cartItems, $metaData);

        $this->assertNotNull($invoice);
        $this->assertEquals(SaleInvoiceStatus::Draft, $invoice->status);
        $this->assertEquals(55.00, (float) $invoice->total_amount);
    }

    public function test_checkout_fails_validation_when_cart_is_empty(): void
    {
        $this->actingAs($this->user);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', [], [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_variant_does_not_exist(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => 999999, // non-existent
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_hold_cart_fails_validation_when_cart_is_empty(): void
    {
        $this->actingAs($this->user);

        Livewire::test(PosTerminal::class)
            ->call('holdCart', [], [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('cart-held-successful');
    }

    public function test_checkout_fails_validation_when_quantity_is_zero_or_negative(): void
    {
        $this->actingAs($this->user);

        $cartZeroQty = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 0,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cartZeroQty, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_price_type_is_invalid(): void
    {
        $this->actingAs($this->user);

        $cartInvalidPriceType = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'invalid_price_type',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cartInvalidPriceType, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_item_discount_amount_is_negative(): void
    {
        $this->actingAs($this->user);

        $cartNegativeDiscount = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
                'discount_type' => 'fixed',
                'discount_amount' => -5.00,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cartNegativeDiscount, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_global_discount_amount_is_negative(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_type' => 'fixed',
                'global_discount_amount' => -10.00,
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_item_discount_amount_provided_without_discount_type(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
                'discount_amount' => 5.00,
                'discount_type' => null,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_item_discount_type_provided_without_discount_amount(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
                'discount_amount' => null,
                'discount_type' => 'fixed',
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_global_discount_amount_provided_without_discount_type(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_amount' => 10.00,
                'global_discount_type' => null,
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_global_discount_type_provided_without_discount_amount(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_amount' => null,
                'global_discount_type' => 'fixed',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_extra_item_is_missing_action_type(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
                'extra_items' => [
                    [
                        'name' => 'Packaging fee',
                        'amount' => 5.00,
                        // missing action_type
                    ],
                ],
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_extra_item_is_missing_name(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
                'extra_items' => [
                    [
                        'amount' => 5.00,
                        'action_type' => 'addition',
                        // missing name
                    ],
                ],
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_extra_item_is_missing_amount(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
                'extra_items' => [
                    [
                        'name' => 'Gift Wrap',
                        'action_type' => 'addition',
                        // missing amount
                    ],
                ],
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_succeeds_validation_when_extra_items_is_empty_or_null(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
                'discount_amount' => 0,
                'discount_type' => 'fixed',
            ],
        ];

        // Should succeed without exception when extra_items is [] or null
        $component = Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
                'extra_items' => [],
                'global_discount_amount' => 0,
                'global_discount_type' => 'fixed',
            ]);

        $component->assertDispatched('checkout-successful');
    }

    public function test_hold_cart_persists_selected_payment_method_and_null_discount_when_not_provided(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'card',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful');

        $invoice = SaleInvoice::latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(SaleInvoiceStatus::Draft, $invoice->status);
        $this->assertSame($this->company->id, $invoice->company_id);
        $this->assertSame($this->store->id, $invoice->store_id);
        $this->assertSame(PaymentMethod::Card, $invoice->payment_method);
        $this->assertNull($invoice->discount_type);
        $this->assertNull($invoice->discount_amount);
    }

    public function test_checkout_persists_proper_company_id_and_store_id(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(SaleInvoiceStatus::Finalized, $invoice->status);
        $this->assertSame($this->company->id, $invoice->company_id);
        $this->assertSame($this->store->id, $invoice->store_id);
    }

    public function test_checkout_fails_validation_when_payment_method_is_missing(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_checkout_fails_validation_when_payment_method_is_invalid(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'unsupported_method',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('checkout-successful');
    }

    public function test_hold_cart_fails_validation_when_payment_method_is_missing(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('cart-held-successful');
    }

    public function test_hold_cart_fails_validation_when_payment_method_is_invalid(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'unsupported_method',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('cart-held-successful');
    }

    public function test_checkout_creates_invoice_with_translated_pos_notes(): void
    {
        $this->actingAs($this->user);

        app()->setLocale('en');

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('Created via POS Terminal', $invoice->notes);
        $this->assertEquals(__('pos.created_via_terminal'), $invoice->notes);
    }

    public function test_checkout_creates_invoice_with_translated_pos_notes_in_arabic(): void
    {
        $this->actingAs($this->user);

        app()->setLocale('ar');

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('تم الإنشاء عبر نقطة البيع', $invoice->notes);
        $this->assertEquals(__('pos.created_via_terminal'), $invoice->notes);
    }

    public function test_hold_cart_creates_draft_invoice_with_translated_pos_notes(): void
    {
        $this->actingAs($this->user);

        app()->setLocale('en');

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(SaleInvoiceStatus::Draft, $invoice->status);
        $this->assertEquals('Created via POS Terminal', $invoice->notes);
        $this->assertEquals(__('pos.created_via_terminal'), $invoice->notes);
    }

    public function test_hold_cart_dispatches_cart_held_successful_event_with_invoice_number_and_total(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 3, // 3 * 20.00 = 60.00
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful', function (string $eventName, array $params): bool {
                $payload = is_array($params[0] ?? null) ? $params[0] : $params;

                return ! empty($payload['invoice_number']) && (float) $payload['total'] === 60.0;
            })
            ->assertNotified();

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertSame(SaleInvoiceStatus::Draft, $invoice->status);
        $this->assertSame(60.0, (float) $invoice->total_amount);
    }

    public function test_hold_cart_with_customer_shipping_and_extra_items_persists_all_draft_relations(): void
    {
        $this->actingAs($this->user);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $destination = ShippingDestination::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'name' => 'Express Courier',
            'cost' => 15.00,
        ]);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 2, // 2 * 20 = 40.00
                'discount_type' => 'fixed',
                'discount_amount' => 2.00, // 2 * 2 = 4.00 discount => 36.00
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'global_discount_type' => 'fixed',
                'global_discount_amount' => 6.00, // 36 - 6 = 30.00
                'shipping_destination_id' => $destination->id,
                'shipping_cost' => 15.00, // 30 + 15 = 45.00
                'shipping_address' => '123 Main St, Nasr City',
                'extra_items' => [
                    [
                        'name' => 'Packaging Service',
                        'action_type' => 'addition',
                        'amount' => 10.00,
                    ],
                    [
                        'name' => 'Promo Voucher',
                        'action_type' => 'subtraction',
                        'amount' => 5.00,
                    ],
                ],
            ])
            ->assertDispatched('cart-held-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertSame(SaleInvoiceStatus::Draft, $invoice->status);
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame($destination->id, $invoice->shipping_destination_id);
        $this->assertSame(15.0, (float) $invoice->shipping_cost);
        $this->assertSame('123 Main St, Nasr City', $invoice->shipping_address);
        $this->assertSame(40.0, (float) $invoice->subtotal);
        $this->assertSame(6.0, (float) $invoice->global_discount_amount);
        $this->assertSame(5.0, (float) $invoice->extra_items_total);
        $this->assertSame(50.0, (float) $invoice->total_amount); // 30 (items post discount) + 5 (net extras) + 15 (shipping) = 50.00
        $this->assertCount(1, $invoice->items);
        $this->assertCount(2, $invoice->extraItems);

        // Assert stock remains completely untouched on hold
        $this->variant->refresh();
        $this->assertSame(50.0, (float) $this->variant->quantity);
    }

    public function test_hold_cart_does_not_deduct_inventory_stock_or_create_inventory_movements(): void
    {
        $this->actingAs($this->user);

        $initialStock = (float) $this->variant->quantity;

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 10,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertSame(SaleInvoiceStatus::Draft, $invoice->status);

        // Verify stock is identical
        $this->variant->refresh();
        $this->assertSame($initialStock, (float) $this->variant->quantity);

        // Verify NO inventory movement was logged
        $movementsCount = InventoryMovement::where('reference_type', SaleInvoice::class)
            ->where('reference_id', $invoice->id)
            ->count();
        $this->assertSame(0, $movementsCount);
    }

    public function test_hold_cart_fails_when_wholesale_threshold_is_not_met(): void
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
            'wholesale_qty_threshold' => 10, // Requires min 10
        ]);

        $cart = [
            [
                'variant_id' => $wholesaleVariant->id,
                'name' => $wholesaleVariant->full_qualified_name,
                'price_type' => 'wholesale',
                'qty' => 3, // Only 3, below threshold of 10
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('cart-held-successful');

        $this->assertDatabaseMissing('sale_invoices', [
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Draft,
        ]);
    }

    public function test_checkout_dispatches_event_with_invoice_id_for_thermal_printing(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        $component = Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)->latest()->first();
        $this->assertNotNull($invoice);

        $dispatch = collect(data_get($component->effects, 'dispatches'))->firstWhere('name', 'checkout-successful');
        $this->assertNotNull($dispatch);
        $payload = $dispatch['params'][0] ?? $dispatch['params'];
        $this->assertEquals($invoice->id, $payload['invoice_id']);
        $this->assertEquals($invoice->invoice_number, $payload['invoice_number']);
        $this->assertEquals((float) $invoice->total_amount, (float) $payload['total']);
    }

    public function test_hold_cart_dispatches_event_with_invoice_id(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        $component = Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful');

        $invoice = SaleInvoice::where('store_id', $this->store->id)
            ->where('status', SaleInvoiceStatus::Draft)
            ->latest()
            ->first();
        $this->assertNotNull($invoice);

        $dispatch = collect(data_get($component->effects, 'dispatches'))->firstWhere('name', 'cart-held-successful');
        $this->assertNotNull($dispatch);
        $payload = $dispatch['params'][0] ?? $dispatch['params'];
        $this->assertEquals($invoice->id, $payload['invoice_id']);
        $this->assertEquals($invoice->invoice_number, $payload['invoice_number']);
    }

    public function test_catalog_paginates_products_with_per_page_setting(): void
    {
        $this->actingAs($this->user);

        // Variant from setUp is 1. Let's create 6 more variants in the same store
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'category_id' => $this->variant->product->category_id,
        ]);

        for ($i = 0; $i < 6; $i++) {
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'is_active' => true,
            ]);
        }

        $component = Livewire::test(PosTerminal::class);

        $viewData = $component->viewData('products');
        $this->assertNotNull($viewData);
        $this->assertEquals(3, $viewData->perPage());
        $this->assertEquals(7, $viewData->total());
        $this->assertCount(3, $viewData->items());
        $this->assertTrue($viewData->hasPages());
        $this->assertEquals(1, $viewData->currentPage());
    }

    public function test_catalog_navigates_between_pages(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'category_id' => $this->variant->product->category_id,
        ]);

        for ($i = 0; $i < 6; $i++) {
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'is_active' => true,
            ]);
        }

        Livewire::test(PosTerminal::class)
            ->call('nextPage')
            ->assertSet('paginators.page', 2)
            ->call('previousPage')
            ->assertSet('paginators.page', 1);
    }

    public function test_search_and_category_filters_reset_page(): void
    {
        $this->actingAs($this->user);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'category_id' => $this->variant->product->category_id,
        ]);

        for ($i = 0; $i < 6; $i++) {
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'is_active' => true,
            ]);
        }

        Livewire::test(PosTerminal::class)
            ->set('paginators.page', 2)
            ->set('search', 'something')
            ->assertSet('paginators.page', 1)
            ->set('paginators.page', 2)
            ->set('categoryId', 999)
            ->assertSet('paginators.page', 1);
    }

    public function test_cashier_can_hold_cart_with_custom_reference_tag(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 3,
                'discount_amount' => 2.0,
                'discount_type' => 'fixed',
            ],
        ];

        $component = Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'hold_reference' => 'Table 14 - Red Car',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful');

        $this->assertDatabaseHas('sale_invoices', [
            'store_id' => $this->store->id,
            'hold_reference' => 'Table 14 - Red Car',
            'status' => SaleInvoiceStatus::Draft->value,
        ]);

        $this->assertEquals(1, $component->get('heldCartsCount'));

        $dispatch = collect(data_get($component->effects, 'dispatches'))->firstWhere('name', 'cart-held-successful');
        $this->assertNotNull($dispatch);
        $payload = $dispatch['params'][0] ?? $dispatch['params'];
        $this->assertEquals('Table 14 - Red Car', $payload['hold_reference']);
    }

    public function test_hold_cart_supports_max_255_character_hold_reference_and_rejects_exceeding_length(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        // 1. Exactly 255 characters should pass and be stored accurately
        $longReference255 = str_repeat('A', 255);

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'hold_reference' => $longReference255,
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful');

        $this->assertDatabaseHas('sale_invoices', [
            'store_id' => $this->store->id,
            'hold_reference' => $longReference255,
            'status' => SaleInvoiceStatus::Draft->value,
        ]);

        // 2. 256 characters should fail validation, notify user, and return cleanly without throwing Halt
        $tooLongReference256 = str_repeat('B', 256);

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'hold_reference' => $tooLongReference256,
                'shipping_cost' => 0,
            ])
            ->assertNotDispatched('cart-held-successful');

        $this->assertDatabaseMissing('sale_invoices', [
            'hold_reference' => $tooLongReference256,
        ]);
    }

    public function test_get_held_invoices_returns_only_active_store_drafts(): void
    {
        $this->actingAs($this->user);

        // Create 2 draft invoices in current store
        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'John Doe',
            'phone' => '1234567890',
        ]);

        $cart1 = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];
        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart1, [
                'payment_method' => 'cash',
                'hold_reference' => 'Order A',
                'customer_id' => $customer->id,
                'shipping_cost' => 0,
            ]);

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart1, [
                'payment_method' => 'card',
                'hold_reference' => 'Order B',
                'shipping_cost' => 0,
            ]);

        // Create 1 draft invoice in a DIFFERENT store
        $otherStore = Store::factory()->create(['company_id' => $this->company->id]);
        $otherUser = User::factory()->create(['company_id' => $this->company->id, 'store_id' => $otherStore->id]);
        $otherVariant = ProductVariant::factory()->withStock(20)->create([
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
            'product_id' => $this->variant->product_id,
            'uom_id' => $this->variant->uom_id,
        ]);
        $this->actingAs($otherUser);
        PosCheckoutService::make()->holdCart(
            [CartItemDTO::fromArray(['variant_id' => $otherVariant->id, 'qty' => 1, 'price_type' => 'retail'])],
            CheckoutMetaDataDTO::fromArray([
                'store_id' => $otherStore->id,
                'company_id' => $this->company->id,
                'payment_method' => 'cash',
                'hold_reference' => 'Other Store Draft',
            ])
        );
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);
        $response = $component->instance()->getHeldInvoices();

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['data']);

        $references = array_column($response['data'], 'hold_reference');
        $this->assertContains('Order A', $references);
        $this->assertContains('Order B', $references);
        $this->assertNotContains('Other Store Draft', $references);

        // Verify structure of preview fields
        $first = $response['data'][0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('invoice_number', $first);
        $this->assertArrayHasKey('hold_reference', $first);
        $this->assertArrayHasKey('customer_name', $first);
        $this->assertArrayHasKey('items_count', $first);
        $this->assertArrayHasKey('items_preview', $first);
        $this->assertArrayHasKey('total_amount', $first);
        $this->assertArrayHasKey('created_at_human', $first);
        $this->assertArrayHasKey('cashier_name', $first);
    }

    public function test_get_held_invoices_filters_by_search_query_across_reference_customer_phone_and_invoice_number(): void
    {
        $this->actingAs($this->user);

        $customer1 = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Ahmed Alpha',
            'phone' => '01011112222',
        ]);

        $customer2 = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Sara Beta',
            'phone' => '01233334444',
        ]);

        $cartItem = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        // 1. Hold cart with reference and customer 1
        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cartItem, [
                'payment_method' => 'cash',
                'hold_reference' => 'VIP Table 7',
                'customer_id' => $customer1->id,
                'shipping_cost' => 0,
            ]);

        // 2. Hold cart with reference and customer 2
        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cartItem, [
                'payment_method' => 'card',
                'hold_reference' => 'Drive Thru 3',
                'customer_id' => $customer2->id,
                'shipping_cost' => 0,
            ]);

        // 3. Hold cart with reference and walk-in customer (no customer_id)
        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cartItem, [
                'payment_method' => 'cash',
                'hold_reference' => 'Takeaway Express',
                'shipping_cost' => 0,
            ]);

        $component = Livewire::test(PosTerminal::class);
        $instance = $component->instance();

        // Total held carts count is 3
        $this->assertEquals(3, $component->get('heldCartsCount'));

        // Case A: Search by hold reference ("VIP")
        $resA = $instance->getHeldInvoices('VIP');
        $this->assertTrue($resA['success']);
        $this->assertCount(1, $resA['data']);
        $this->assertEquals('VIP Table 7', $resA['data'][0]['hold_reference']);
        $this->assertEquals('Ahmed Alpha', $resA['data'][0]['customer_name']);

        // Case B: Search by customer name ("Sara")
        $resB = $instance->getHeldInvoices('Sara');
        $this->assertTrue($resB['success']);
        $this->assertCount(1, $resB['data']);
        $this->assertEquals('Drive Thru 3', $resB['data'][0]['hold_reference']);
        $this->assertEquals('Sara Beta', $resB['data'][0]['customer_name']);

        // Case C: Search by customer phone ("0101111")
        $resC = $instance->getHeldInvoices('0101111');
        $this->assertTrue($resC['success']);
        $this->assertCount(1, $resC['data']);
        $this->assertEquals('VIP Table 7', $resC['data'][0]['hold_reference']);

        // Case D: Search by invoice number
        $takeawayDraft = SaleInvoice::where('hold_reference', 'Takeaway Express')->first();
        $this->assertNotNull($takeawayDraft);
        $resD = $instance->getHeldInvoices($takeawayDraft->invoice_number);
        $this->assertTrue($resD['success']);
        $this->assertCount(1, $resD['data']);
        $this->assertEquals('Takeaway Express', $resD['data'][0]['hold_reference']);

        // Case E: Search with non-matching string
        $resE = $instance->getHeldInvoices('NonExistentQueryXYZ');
        $this->assertTrue($resE['success']);
        $this->assertCount(0, $resE['data']);

        // Assert search does NOT overwrite heldCartsCount badge
        $this->assertEquals(3, $component->get('heldCartsCount'));

        // Case F: Blank/null search returns all store drafts
        $resF = $instance->getHeldInvoices(null);
        $this->assertTrue($resF['success']);
        $this->assertCount(3, $resF['data']);
    }

    public function test_fetch_draft_invoice_rehydrates_complete_cart_data_accurately(): void
    {
        $this->actingAs($this->user);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Jane Smith',
        ]);

        $destination = ShippingDestination::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'name' => 'Downtown Express',
            'cost' => 15.00,
        ]);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 4,
                'discount_amount' => 5.0,
                'discount_type' => 'fixed',
            ],
        ];

        $extraItems = [
            [
                'name' => 'Gift Wrap',
                'amount' => 3.50,
                'action_type' => 'addition',
                'notes' => 'Blue Ribbon',
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'card',
                'hold_reference' => 'VIP Table',
                'customer_id' => $customer->id,
                'global_discount_type' => 'fixed',
                'global_discount_amount' => 10.0,
                'shipping_destination_id' => $destination->id,
                'shipping_cost' => 15.0,
                'shipping_address' => '123 Main St, Apt 4B',
                'extra_items' => $extraItems,
            ]);

        $invoice = SaleInvoice::where('store_id', $this->store->id)->draft()->first();
        $this->assertNotNull($invoice);

        $component = Livewire::test(PosTerminal::class);
        $response = $component->instance()->fetchDraftInvoice($invoice->id);

        $this->assertTrue($response['success']);
        $data = $response['data'];

        $this->assertEquals($invoice->id, $data['id']);
        $this->assertEquals($invoice->invoice_number, $data['invoice_number']);
        $this->assertEquals('VIP Table', $data['hold_reference']);
        $this->assertEquals($customer->id, $data['customer_id']);
        $this->assertEquals('Jane Smith', $data['customer_name']);
        $this->assertEquals('card', $data['payment_method']);
        $this->assertEquals('fixed', $data['global_discount_type']);
        $this->assertEquals(10.0, $data['global_discount_amount']);
        $this->assertEquals($destination->id, $data['shipping_destination_id']);
        $this->assertEquals(15.0, $data['shipping_cost']);
        $this->assertEquals('123 Main St, Apt 4B', $data['shipping_address']);
        $this->assertFalse($data['has_stock_warning']);

        $this->assertCount(1, $data['extra_items']);
        $this->assertEquals('Gift Wrap', $data['extra_items'][0]['name']);
        $this->assertEquals(3.5, $data['extra_items'][0]['amount']);
        $this->assertEquals('addition', $data['extra_items'][0]['action_type']);

        $this->assertCount(1, $data['cart_items']);
        $item = $data['cart_items'][0];
        $this->assertEquals($this->variant->id, $item['variant_id']);
        $this->assertEquals(4.0, $item['qty']);
        $this->assertEquals('retail', $item['priceType']);
        $this->assertEquals('fixed', $item['discountType']);
        $this->assertEquals(5.0, $item['discountAmount']);
        $this->assertFalse($item['stock_warning']);
    }

    public function test_fetch_draft_invoice_flags_stock_warning_when_variant_stock_is_insufficient(): void
    {
        $this->actingAs($this->user);

        // Create variant with stock = 10
        $lowStockVariant = ProductVariant::factory()->withStock(10)->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $this->variant->product_id,
            'uom_id' => $this->variant->uom_id,
        ]);

        // Hold cart requesting 5 units (valid when held)
        $invoice = PosCheckoutService::make()->holdCart(
            [CartItemDTO::fromArray(['variant_id' => $lowStockVariant->id, 'qty' => 5, 'price_type' => 'retail'])],
            CheckoutMetaDataDTO::fromArray([
                'store_id' => $this->store->id,
                'company_id' => $this->company->id,
                'payment_method' => 'cash',
                'hold_reference' => 'Stock Test',
            ])
        );

        // Later, physical stock drops to 2 (e.g. sold elsewhere)
        $lowStockVariant->update(['quantity' => 2]);

        $component = Livewire::test(PosTerminal::class);
        $response = $component->instance()->fetchDraftInvoice($invoice->id);

        $this->assertTrue($response['success']);
        $data = $response['data'];

        $this->assertTrue($data['has_stock_warning']);
        $this->assertCount(1, $data['cart_items']);
        $this->assertTrue($data['cart_items'][0]['stock_warning']);
        $this->assertEquals(2.0, $data['cart_items'][0]['stock']);
        $this->assertEquals(5.0, $data['cart_items'][0]['qty']);
    }

    public function test_checkout_resumed_draft_finalizes_in_place_without_duplicate_invoice(): void
    {
        $this->actingAs($this->user);

        // 1. Initial hold
        $cartInitial = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cartInitial, [
                'payment_method' => 'cash',
                'hold_reference' => 'Table Resumed',
                'shipping_cost' => 0,
            ]);

        $initialDraft = SaleInvoice::where('store_id', $this->store->id)->draft()->first();
        $this->assertNotNull($initialDraft);
        $draftId = $initialDraft->id;
        $draftNumber = $initialDraft->invoice_number;

        $this->assertEquals(1, SaleInvoice::count());

        // 2. Checkout resumed draft with updated quantity (3 units) and draft_invoice_id
        $cartUpdated = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 3,
            ],
        ];

        $component = Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cartUpdated, [
                'payment_method' => 'cash',
                'draft_invoice_id' => $draftId,
                'hold_reference' => 'Table Resumed',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('checkout-successful');

        // Verify NO duplicate invoice was created!
        $this->assertEquals(1, SaleInvoice::count());

        // Verify the existing invoice was finalized in-place
        $finalizedInvoice = SaleInvoice::find($draftId);
        $this->assertNotNull($finalizedInvoice);
        $this->assertEquals($draftNumber, $finalizedInvoice->invoice_number);
        $this->assertEquals(SaleInvoiceStatus::Finalized, $finalizedInvoice->status);
        $this->assertEquals(3 * 20.00, (float) $finalizedInvoice->total_amount);

        // Verify inventory movements occurred for the finalized items
        $this->assertDatabaseHas('inventory_movements', [
            'store_id' => $this->store->id,
            'variant_id' => $this->variant->id,
            'type' => MovementType::Sale->value,
        ]);
        $this->assertEquals(47.0, (float) $this->variant->fresh()->quantity);

        // Verify heldCartsCount is now 0
        $this->assertEquals(0, $component->get('heldCartsCount'));
    }

    public function test_reholding_resumed_draft_updates_draft_in_place_without_duplicate_invoice(): void
    {
        $this->actingAs($this->user);

        // 1. Initial hold
        $cartInitial = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 1,
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cartInitial, [
                'payment_method' => 'cash',
                'hold_reference' => 'Table Old Note',
                'shipping_cost' => 0,
            ]);

        $initialDraft = SaleInvoice::where('store_id', $this->store->id)->draft()->first();
        $draftId = $initialDraft->id;

        $this->assertEquals(1, SaleInvoice::count());

        // 2. Re-hold with draft_invoice_id, updated reference, and updated qty
        $cartUpdated = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 4,
            ],
        ];

        $component = Livewire::test(PosTerminal::class)
            ->call('holdCart', $cartUpdated, [
                'payment_method' => 'cash',
                'draft_invoice_id' => $draftId,
                'hold_reference' => 'Table New Note',
                'shipping_cost' => 0,
            ])
            ->assertDispatched('cart-held-successful');

        // Verify NO duplicate invoice was created
        $this->assertEquals(1, SaleInvoice::count());

        $updatedDraft = SaleInvoice::find($draftId);
        $this->assertEquals(SaleInvoiceStatus::Draft, $updatedDraft->status);
        $this->assertEquals('Table New Note', $updatedDraft->hold_reference);
        $this->assertEquals(1, $updatedDraft->items()->count());
        $this->assertEquals(4.0, (float) $updatedDraft->items()->first()->quantity);

        $this->assertEquals(1, $component->get('heldCartsCount'));
    }

    public function test_discard_draft_invoice_deletes_record_and_cascades_relations(): void
    {
        $this->actingAs($this->user);

        $cart = [
            [
                'variant_id' => $this->variant->id,
                'name' => $this->variant->full_qualified_name,
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        $extraItems = [
            [
                'name' => 'Packing Box',
                'amount' => 5.0,
                'action_type' => 'addition',
            ],
        ];

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'hold_reference' => 'To Discard',
                'extra_items' => $extraItems,
                'shipping_cost' => 0,
            ]);

        $invoice = SaleInvoice::where('store_id', $this->store->id)->draft()->first();
        $this->assertNotNull($invoice);
        $invoiceId = $invoice->id;

        $component = Livewire::test(PosTerminal::class);
        $this->assertEquals(1, $component->get('heldCartsCount'));

        $response = $component->instance()->discardDraftInvoice($invoiceId);

        $this->assertTrue($response['success']);
        $this->assertDatabaseMissing('sale_invoices', ['id' => $invoiceId]);
        $this->assertDatabaseMissing('sale_invoice_items', ['sale_invoice_id' => $invoiceId]);
        $this->assertDatabaseMissing('sale_invoice_extra_items', ['sale_invoice_id' => $invoiceId]);

        $this->assertEquals(0, $component->get('heldCartsCount'));
    }

    public function test_cannot_fetch_or_discard_draft_invoice_from_another_store(): void
    {
        $this->actingAs($this->user);

        $otherStore = Store::factory()->create(['company_id' => $this->company->id]);
        $otherUser = User::factory()->create(['company_id' => $this->company->id, 'store_id' => $otherStore->id]);
        $otherVariant = ProductVariant::factory()->withStock(10)->create([
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
            'product_id' => $this->variant->product_id,
            'uom_id' => $this->variant->uom_id,
        ]);

        $this->actingAs($otherUser);
        $otherInvoice = PosCheckoutService::make()->holdCart(
            [CartItemDTO::fromArray(['variant_id' => $otherVariant->id, 'qty' => 1, 'price_type' => 'retail'])],
            CheckoutMetaDataDTO::fromArray([
                'store_id' => $otherStore->id,
                'company_id' => $this->company->id,
                'payment_method' => 'cash',
                'hold_reference' => 'Protected Store B',
            ])
        );
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);

        // Attempt to fetch other store's draft
        $fetchResponse = $component->instance()->fetchDraftInvoice($otherInvoice->id);
        $this->assertFalse($fetchResponse['success']);

        // Attempt to discard other store's draft
        $discardResponse = $component->instance()->discardDraftInvoice($otherInvoice->id);
        $this->assertFalse($discardResponse['success']);

        // Assert record is untouched
        $this->assertDatabaseHas('sale_invoices', ['id' => $otherInvoice->id]);
    }

    public function test_thermal_receipt_print_route_displays_hold_reference_and_draft_disclaimer(): void
    {
        Permission::firstOrCreate(['name' => 'view_sale_invoice', 'guard_name' => 'web']);
        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo('view_sale_invoice');

        $this->actingAs($this->user);

        $invoice = PosCheckoutService::make()->holdCart(
            [CartItemDTO::fromArray(['variant_id' => $this->variant->id, 'qty' => 2, 'price_type' => 'retail'])],
            CheckoutMetaDataDTO::fromArray([
                'store_id' => $this->store->id,
                'company_id' => $this->company->id,
                'payment_method' => 'cash',
                'hold_reference' => 'Table VIP 99',
            ])
        );

        $response = $this->get("/print/invoice/sale_invoice/{$invoice->id}");

        $response->assertOk();
        $response->assertSee('Table VIP 99');
        $response->assertSee(__('app.draft_invoice_disclaimer'));
    }

    public function test_process_checkout_handles_validation_failure_gracefully_without_throwing_halt(): void
    {
        $this->actingAs($this->user);

        // Call processCheckout with empty cart -> validation fails
        Livewire::test(PosTerminal::class)
            ->call('processCheckout', [], [
                'payment_method' => 'cash',
            ])
            ->assertNotDispatched('checkout-successful');

        $this->assertDatabaseCount('sale_invoices', 0);
    }

    public function test_create_shipping_destination_validates_and_handles_exceptions_via_rpc_response(): void
    {
        $this->actingAs($this->user);
        $component = Livewire::test(PosTerminal::class);
        $instance = $component->instance();

        // 1. Validation error: missing name and cost
        $invalidRes = $instance->createShippingDestination([]);
        $this->assertFalse($invalidRes['success']);
        $this->assertArrayHasKey('name', $invalidRes['errors']);
        $this->assertArrayHasKey('cost', $invalidRes['errors']);

        // 2. Success path
        $validRes = $instance->createShippingDestination([
            'name' => 'Northern Suburbs',
            'cost' => 12.5,
        ]);
        $this->assertTrue($validRes['success']);
        $this->assertEquals('Northern Suburbs', $validRes['data']['name']);
        $this->assertEquals(12.5, $validRes['data']['cost']);
        $this->assertDatabaseHas('shipping_destinations', [
            'name' => 'Northern Suburbs',
            'cost' => 12.5,
        ]);

        // Verify public component list was appended
        $destinations = $component->get('shippingDestinationList');
        $this->assertContains('Northern Suburbs', array_column($destinations, 'name'));
    }

    public function test_create_customer_validates_and_handles_exceptions_via_rpc_response(): void
    {
        $this->actingAs($this->user);
        $component = Livewire::test(PosTerminal::class);
        $instance = $component->instance();

        // 1. Validation error: missing name
        $invalidRes = $instance->createCustomer([]);
        $this->assertFalse($invalidRes['success']);
        $this->assertArrayHasKey('name', $invalidRes['errors']);

        // 2. Success path
        $validRes = $instance->createCustomer([
            'name' => 'Grace Hopper',
            'phone' => '01122334455',
        ]);
        $this->assertTrue($validRes['success']);
        $this->assertEquals('Grace Hopper', $validRes['data']['name']);
        $this->assertDatabaseHas('customers', [
            'name' => 'Grace Hopper',
            'phone' => '01122334455',
        ]);

        // Verify public component list was appended
        $customers = $component->get('customerList');
        $this->assertContains('Grace Hopper', array_column($customers, 'name'));
    }

    public function test_change_store_handles_unauthorized_and_invalid_stores_gracefully(): void
    {
        $this->actingAs($this->user);

        // Store-level user cannot change store
        $otherStore = Store::factory()->create(['company_id' => $this->company->id]);
        $component = Livewire::test(PosTerminal::class)
            ->call('changeStore', $otherStore->id);

        // Store ID must remain unchanged for store-level user
        $this->assertEquals($this->store->id, $component->get('storeId'));

        // Admin (company-level) user attempting to switch to non-existent store
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null, // Company-level
        ]);
        $this->actingAs($admin);

        Livewire::test(PosTerminal::class)
            ->call('changeStore', 999999); // Non-existent store ID completes without 500 error
    }

    public function test_get_extra_item_presets_handles_exception_gracefully(): void
    {
        $this->actingAs($this->user);

        try {
            InvoiceExtraItemPreset::addGlobalScope('simulate_failure', function () {
                throw new \RuntimeException('Simulated query failure');
            });

            $component = Livewire::test(PosTerminal::class);
            $component->call('getExtraItemPresets')
                ->assertNotified(__('pos.extra_item_presets_load_failed'));

            $this->assertSame([], $component->instance()->getExtraItemPresets());
        } finally {
            $reflection = new \ReflectionClass(InvoiceExtraItemPreset::class);
            $property = $reflection->getProperty('globalScopes');
            $scopes = $property->getValue();
            unset($scopes[InvoiceExtraItemPreset::class]['simulate_failure']);
            $property->setValue(null, $scopes);
        }
    }

    public function test_fetch_draft_invoice_handles_exception_gracefully(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);
        $result = $component->call('fetchDraftInvoice', 999999);

        $result->assertNotified(__('pos.draft_resume_failed'));
        $response = $result->instance()->fetchDraftInvoice(999999);
        $this->assertFalse($response['success']);
        $this->assertNotNull($response['message']);
    }

    public function test_discard_draft_invoice_handles_exception_gracefully(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(PosTerminal::class);
        $result = $component->call('discardDraftInvoice', 999999);

        $result->assertNotified(__('pos.draft_discard_failed'));
        $response = $result->instance()->discardDraftInvoice(999999);
        $this->assertFalse($response['success']);
        $this->assertNotNull($response['message']);
    }

    public function test_get_held_invoices_handles_missing_store_with_danger_notification(): void
    {
        $admin = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null, // Company-level, no store selected initially
        ]);
        $this->actingAs($admin);

        $component = Livewire::test(PosTerminal::class);
        $result = $component->call('getHeldInvoices');

        $result->assertNotified(__('pos.select_store_first'));
        $response = $result->instance()->getHeldInvoices();
        $this->assertFalse($response['success']);
    }
}
