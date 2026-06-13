<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\ExtraItemActionType;
use App\Enums\InvoiceReturnStatus;
use App\Enums\MovementType;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceExtraItem;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PurchaseInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Company $company;

    private User $user;

    private Vendor $vendor;

    private ProductVariant $variant1;

    private ProductVariant $variant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->store = Store::factory()->create(['company_id' => $this->company->id]);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->vendor = Vendor::create([
            'company_id' => $this->company->id,
            'name' => 'Test Vendor',
        ]);

        $product1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $this->variant1 = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product1->id,
            'quantity' => 0,
        ]);

        $product2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $this->variant2 = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product2->id,
            'quantity' => 0,
        ]);

        Auth::login($this->user);
    }

    private function createDraftInvoice(array $attributes = []): PurchaseInvoice
    {
        return PurchaseInvoice::create(array_merge([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'vendor_id' => $this->vendor->id,
            'status' => PurchaseInvoiceStatus::Draft,
            'return_status' => InvoiceReturnStatus::None,
            'vendor_invoice_ref' => '123',
            'invoice_number' => 'INV-'.rand(1000, 9999),
            'date' => now(),
            'due_date' => now(),
            'received_at' => now(),
        ], $attributes));
    }

    private function createItem($invoiceId, $variantId, $cost, $quantity, $discountType = null, $discountAmount = null): PurchaseInvoiceItem
    {
        return PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoiceId,
            'product_variant_id' => $variantId,
            'unit_cost' => $cost,
            'quantity' => $quantity,
            'discount_type' => $discountType,
            'unit_discount_amount' => $discountAmount,
            'subtotal' => 0,
            'tax_amount' => 0,
            'line_total' => 0,
            'line_total_discount' => 0,
            'tax_rate' => 0,
        ]);
    }

    public function test_recalculate_totals_with_no_discounts(): void
    {
        $invoice = $this->createDraftInvoice();
        $this->createItem($invoice->id, $this->variant1->id, 100, 2);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(0, $invoice->global_discount_amount);
        $this->assertEquals(0, $invoice->grand_total_discount);
        $this->assertEquals(200, $invoice->total_before_tax);
        $this->assertEquals(200, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_fixed_item_discount(): void
    {
        $invoice = $this->createDraftInvoice();
        $item = $this->createItem($invoice->id, $this->variant1->id, 100, 2, DiscountType::Fixed, 10);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();
        $item->refresh();

        $this->assertEquals(20, $item->line_total_discount);
        $this->assertEquals(180, $item->line_total);

        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(0, $invoice->global_discount_amount);
        $this->assertEquals(20, $invoice->grand_total_discount);
        $this->assertEquals(180, $invoice->total_before_tax);
        $this->assertEquals(180, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_percentage_item_discount(): void
    {
        $invoice = $this->createDraftInvoice();
        $item = $this->createItem($invoice->id, $this->variant1->id, 200, 3, DiscountType::Percentage, 10);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();
        $item->refresh();

        // 10% of 200 is 20 per unit. Quantity 3 = 60 discount. Line total = 600 - 60 = 540.
        $this->assertEquals(60, $item->line_total_discount);
        $this->assertEquals(540, $item->line_total);

        $this->assertEquals(600, $invoice->subtotal);
        $this->assertEquals(60, $invoice->grand_total_discount);
        $this->assertEquals(540, $invoice->total_before_tax);
        $this->assertEquals(540, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_multiple_items_and_mixed_discounts(): void
    {
        $invoice = $this->createDraftInvoice();
        $this->createItem($invoice->id, $this->variant1->id, 100, 2, DiscountType::Fixed, 15);
        $this->createItem($invoice->id, $this->variant2->id, 50, 4, DiscountType::Percentage, 20);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        // Item 1: 100 * 2 = 200. Discount = 15 * 2 = 30. Line total = 170.
        // Item 2: 50 * 4 = 200. Discount = (50 * 20%) * 4 = 10 * 4 = 40. Line total = 160.
        // Subtotal = 400. Items Total Discount = 70.
        // Total Before Tax = 170 + 160 = 330.

        $this->assertEquals(400, $invoice->subtotal);
        $this->assertEquals(70, $invoice->grand_total_discount);
        $this->assertEquals(330, $invoice->total_before_tax);
        $this->assertEquals(330, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_fixed_global_discount(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->update([
            'discount_type' => DiscountType::Fixed,
            'discount_amount' => 50,
        ]);

        $this->createItem($invoice->id, $this->variant1->id, 100, 2);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(50, $invoice->global_discount_amount);
        $this->assertEquals(50, $invoice->grand_total_discount);
        $this->assertEquals(200, $invoice->total_before_tax);
        $this->assertEquals(150, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_percentage_global_discount(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->update([
            'discount_type' => DiscountType::Percentage,
            'discount_amount' => 10, // 10%
        ]);

        $this->createItem($invoice->id, $this->variant1->id, 100, 2);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        // 10% of 200 is 20.
        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(20, $invoice->global_discount_amount);
        $this->assertEquals(20, $invoice->grand_total_discount);
        $this->assertEquals(200, $invoice->total_before_tax);
        $this->assertEquals(180, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_item_discounts_and_global_fixed_discount(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->update([
            'discount_type' => DiscountType::Fixed,
            'discount_amount' => 30,
        ]);

        $this->createItem($invoice->id, $this->variant1->id, 100, 2, DiscountType::Fixed, 10);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        // Items Subtotal: 200
        // Item Discount: 20
        // Total Before Tax (after item discount): 180
        // Global Discount: 30
        // Grand Total Discount: 20 + 30 = 50
        // Total Amount: 180 - 30 = 150

        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(30, $invoice->global_discount_amount);
        $this->assertEquals(50, $invoice->grand_total_discount);
        $this->assertEquals(180, $invoice->total_before_tax);
        $this->assertEquals(150, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_item_discounts_and_global_percentage_discount(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->update([
            'discount_type' => DiscountType::Percentage,
            'discount_amount' => 10, // 10% global
        ]);

        $this->createItem($invoice->id, $this->variant1->id, 100, 2, DiscountType::Fixed, 10);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        // 10% global of 180 = 18.
        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(18, $invoice->global_discount_amount);
        $this->assertEquals(38, $invoice->grand_total_discount); // 20 + 18
        $this->assertEquals(180, $invoice->total_before_tax);
        $this->assertEquals(162, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_extra_items_addition(): void
    {
        $invoice = $this->createDraftInvoice();

        $this->createItem($invoice->id, $this->variant1->id, 100, 2);

        PurchaseInvoiceExtraItem::create([
            'purchase_invoice_id' => $invoice->id,
            'action_type' => ExtraItemActionType::Addition,
            'amount' => 50,
            'name' => 'Shipping',
        ]);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        $this->assertEquals(50, $invoice->extra_items_total);
        $this->assertEquals(200, $invoice->total_before_tax);
        $this->assertEquals(250, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_extra_items_subtraction(): void
    {
        $invoice = $this->createDraftInvoice();

        $this->createItem($invoice->id, $this->variant1->id, 100, 2);

        PurchaseInvoiceExtraItem::create([
            'purchase_invoice_id' => $invoice->id,
            'action_type' => ExtraItemActionType::Subtraction,
            'amount' => 30,
            'name' => 'Vendor Credit',
        ]);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        $this->assertEquals(-30, $invoice->extra_items_total);
        $this->assertEquals(200, $invoice->total_before_tax);
        $this->assertEquals(170, $invoice->total_amount);
    }

    public function test_recalculate_totals_with_discounts_and_extra_items(): void
    {
        $invoice = $this->createDraftInvoice();
        $invoice->update([
            'discount_type' => DiscountType::Fixed,
            'discount_amount' => 20,
        ]);

        $this->createItem($invoice->id, $this->variant1->id, 100, 2, DiscountType::Percentage, 5);

        PurchaseInvoiceExtraItem::create([
            'purchase_invoice_id' => $invoice->id,
            'action_type' => ExtraItemActionType::Addition,
            'amount' => 30, // Extra = +30
            'name' => 'Shipping',
        ]);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        // Items Subtotal = 200. Item Discount = 10.
        // Total Before Tax = 190.
        // Global Discount = 20.
        // Grand Total Discount = 30.
        // Extra Items = +30.
        // Total Amount = 190 - 20 + 30 = 200.

        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(10, $invoice->grand_total_discount - $invoice->global_discount_amount);
        $this->assertEquals(20, $invoice->global_discount_amount);
        $this->assertEquals(30, $invoice->grand_total_discount);
        $this->assertEquals(30, $invoice->extra_items_total);
        $this->assertEquals(190, $invoice->total_before_tax);
        $this->assertEquals(200, $invoice->total_amount);
    }

    public function test_recalculate_totals_clamps_total_amount_to_zero(): void
    {
        $invoice = $this->createDraftInvoice();

        $this->createItem($invoice->id, $this->variant1->id, 10, 1);

        PurchaseInvoiceExtraItem::create([
            'purchase_invoice_id' => $invoice->id,
            'action_type' => ExtraItemActionType::Subtraction,
            'amount' => 100,
            'name' => 'Discount',
        ]);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        $this->assertEquals(10, $invoice->total_before_tax);
        $this->assertEquals(-100, $invoice->extra_items_total);
        $this->assertEquals(-90, $invoice->total_amount); // Kept negative
    }

    public function test_finalize_locks_invoice_and_updates_stock(): void
    {
        $invoice = $this->createDraftInvoice();
        $this->createItem($invoice->id, $this->variant1->id, 100, 10);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        PurchaseInvoiceService::make()->finalize($invoice);

        $invoice->refresh();
        $this->variant1->refresh();

        $this->assertEquals(PurchaseInvoiceStatus::Finalized, $invoice->status);
        $this->assertEquals(100, $this->variant1->purchase_price);
        $this->assertEquals(10, $this->variant1->quantity); // Stock goes up by 10

        $this->assertDatabaseHas(InventoryMovement::class, [
            'variant_id' => $this->variant1->id,
            'type' => MovementType::StockIn->value,
            'quantity' => 10,
        ]);
    }

    public function test_finalize_return_updates_stock_and_status(): void
    {
        $invoice = $this->createDraftInvoice();
        $item = $this->createItem($invoice->id, $this->variant1->id, 100, 10);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        PurchaseInvoiceService::make()->finalize($invoice);
        $this->variant1->refresh();
        $this->assertEquals(10, $this->variant1->quantity);

        $return = PurchaseReturn::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => PurchaseReturnStatus::Draft,
            'return_number' => 'PR-123',
            'date' => now(),
            'returned_at' => now(),
            'return_reason' => 'defective',
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $return->id,
            'product_variant_id' => $this->variant1->id,
            'original_item_id' => $item->id,
            'quantity' => 4,
            'unit_cost' => 100,
            'subtotal' => 0,
            'tax_amount' => 0,
            'line_total' => 0,
            'line_total_discount' => 0,
            'tax_rate' => 0,
        ]);

        PurchaseInvoiceService::make()->recalculateReturnTotals($return);
        PurchaseInvoiceService::make()->finalizeReturn($return);

        $return->refresh();
        $invoice->refresh();
        $this->variant1->refresh();

        $this->assertEquals(PurchaseReturnStatus::Finalized, $return->status);
        $this->assertEquals(InvoiceReturnStatus::PartiallyReturned, $invoice->return_status);
        $this->assertEquals(6, $this->variant1->quantity); // Started at 10, returned 4

        $this->assertDatabaseHas(InventoryMovement::class, [
            'variant_id' => $this->variant1->id,
            'type' => MovementType::PurchaseReturn->value,
            'quantity' => 4,
        ]);
    }

    public function test_finalize_return_rejects_exceeding_returnable_quantity(): void
    {
        $invoice = $this->createDraftInvoice();
        $item = $this->createItem($invoice->id, $this->variant1->id, 100, 5);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        PurchaseInvoiceService::make()->finalize($invoice);

        $return = PurchaseReturn::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => PurchaseReturnStatus::Draft,
            'return_number' => 'PR-123',
            'date' => now(),
            'returned_at' => now(),
            'return_reason' => 'defective',
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $return->id,
            'product_variant_id' => $this->variant1->id,
            'original_item_id' => $item->id,
            'quantity' => 10, // Exceeds original qty 5
            'unit_cost' => 100,
            'subtotal' => 0,
            'tax_amount' => 0,
            'line_total' => 0,
            'line_total_discount' => 0,
            'tax_rate' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        PurchaseInvoiceService::make()->finalizeReturn($return);
    }

    public function test_monetary_unit_discount_amount_helper(): void
    {
        $item = new PurchaseInvoiceItem([
            'unit_cost' => 200,
            'discount_type' => DiscountType::Fixed,
            'unit_discount_amount' => 15,
        ]);
        $this->assertEquals(15, $item->monetary_unit_discount_amount);

        $item2 = new PurchaseInvoiceItem([
            'unit_cost' => 200,
            'discount_type' => DiscountType::Percentage,
            'unit_discount_amount' => 15, // 15%
        ]);
        $this->assertEquals(30, $item2->monetary_unit_discount_amount);

        $item3 = new PurchaseInvoiceItem([
            'unit_cost' => 200,
            'discount_type' => null,
            'unit_discount_amount' => 15,
        ]);
        $this->assertEquals(0, $item3->monetary_unit_discount_amount);
    }

    public function test_line_total_discount_helper(): void
    {
        $item = new PurchaseInvoiceItem([
            'unit_cost' => 200,
            'quantity' => 3,
            'discount_type' => DiscountType::Percentage,
            'unit_discount_amount' => 10, // 10% = 20 per unit. Total = 60
        ]);
        $this->assertEquals(60, $item->lineTotalDiscount());
        $this->assertEquals(600, $item->subtotalBeforeItemDiscount);
        $this->assertEquals(540, $item->subtotalAfterItemDiscount());
    }

    public function test_calculate_refund_breakdown_with_discounts(): void
    {
        $invoice = $this->createDraftInvoice([
            'discount_type' => 'percentage',
            'discount_amount' => 10, // 10% global
        ]);

        $item1 = $this->createItem($invoice->id, $this->variant1->id, 100, 2); // Subtotal: 200
        $item2 = $this->createItem($invoice->id, $this->variant2->id, 150, 2, 'fixed', 25); // Subtotal after discount: 150 * 2 - (25*2) = 250

        PurchaseInvoiceService::make()->recalculateTotals($invoice);

        $item1->refresh();
        $item2->refresh();

        // Subtotals after item discounts: 200 + 250 = 450
        // Global discount 10% on 450 = 45
        // Item 1 weight: 200 / 450. Prorated global: (200 / 450) * 45 = 20. Unit prorated: 20 / 2 = 10
        // Item 1 effective unit refund: (200 - 20) / 2 = 90

        $breakdown1 = PurchaseInvoiceService::make()->calculateRefundBreakdown($item1);
        $this->assertEquals(10, $breakdown1['unit_prorated_global_discount']);
        $this->assertEquals(90, $breakdown1['effective_unit_refund']);

        // Item 2 weight: 250 / 450. Prorated global: (250 / 450) * 45 = 25. Unit prorated: 25 / 2 = 12.5
        // Item 2 effective unit refund: (250 - 25) / 2 = 112.5

        $breakdown2 = PurchaseInvoiceService::make()->calculateRefundBreakdown($item2);
        $this->assertEquals(12.5, $breakdown2['unit_prorated_global_discount']);
        $this->assertEquals(112.5, $breakdown2['effective_unit_refund']);
    }

    public function test_recalculate_return_totals_with_discounts(): void
    {
        $invoice = $this->createDraftInvoice([
            'discount_type' => 'percentage',
            'discount_amount' => 10, // 10% global
        ]);

        $item1 = $this->createItem($invoice->id, $this->variant1->id, 100, 2); // Unit cost 100. Effective refund 90
        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        PurchaseInvoiceService::make()->finalize($invoice);

        $return = PurchaseReturn::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => PurchaseReturnStatus::Draft,
            'return_number' => 'PR-123',
            'date' => now(),
            'returned_at' => now(),
            'return_reason' => 'defective',
        ]);

        $item1->refresh();

        $breakdown = PurchaseInvoiceService::make()->calculateRefundBreakdown($item1);

        $returnItem = PurchaseReturnItem::create([
            'purchase_return_id' => $return->id,
            'original_item_id' => $item1->id,
            'product_variant_id' => $this->variant1->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'unit_discount_amount' => 0,
            'unit_prorated_global_discount' => $breakdown['unit_prorated_global_discount'],
            'effective_unit_refund' => $breakdown['effective_unit_refund'],
            'subtotal' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => 0,
        ]);

        PurchaseInvoiceService::make()->recalculateReturnTotals($return);

        $return->refresh();
        $this->assertEquals(90, $return->subtotal);
        $this->assertEquals(90, $return->total_amount);

        $returnItem->refresh();
        $this->assertEquals(90, $returnItem->subtotal);
        $this->assertEquals(90, $returnItem->line_total);
    }
}
