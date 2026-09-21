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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);

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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);

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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                'price_type' => 'retail',
                'qty' => 2,
            ],
        ];

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_type' => 'fixed',
                'global_discount_amount' => 25.00,
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
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
                discountType: null,
                discountAmount: 0.0,
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
                priceType: PriceType::Retail
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', [], [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);
    }

    public function test_hold_cart_fails_validation_when_cart_is_empty(): void
    {
        $this->actingAs($this->user);

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('holdCart', [], [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cartZeroQty, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cartInvalidPriceType, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cartNegativeDiscount, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_type' => 'fixed',
                'global_discount_amount' => -10.00,
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_amount' => 10.00,
                'global_discount_type' => null,
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'cash',
                'global_discount_amount' => null,
                'global_discount_type' => 'fixed',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

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
            ]);
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

        $this->expectException(Halt::class);

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
            ]);
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

        $this->expectException(Halt::class);

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
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('processCheckout', $cart, [
                'payment_method' => 'unsupported_method',
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'shipping_cost' => 0,
            ]);
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

        $this->expectException(Halt::class);

        Livewire::test(PosTerminal::class)
            ->call('holdCart', $cart, [
                'payment_method' => 'unsupported_method',
                'shipping_cost' => 0,
            ]);
    }
}
