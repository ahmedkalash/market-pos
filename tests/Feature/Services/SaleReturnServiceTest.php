<?php

namespace Tests\Feature\Services;

use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\SaleInvoiceReturnStatus;
use App\Enums\SaleInvoiceStatus;
use App\Enums\SaleReturnStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnInvoice;
use App\Models\SaleReturnInvoiceItem;
use App\Models\Store;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
    }

    private function createVariantWithStock(int $qty = 100, float $price = 100): ProductVariant
    {
        $product = Product::factory()->create(['store_id' => $this->store->id]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'retail_price' => $price,
            'quantity' => $qty,
        ]);

        return $variant;
    }

    /**
     * test_recalculate_return_totals_simple_no_discounts
     */
    public function test_recalculate_return_totals_simple_no_discounts()
    {
        $variant = $this->createVariantWithStock();

        $invoice = SaleInvoice::factory()->create([
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
            'subtotal' => 500,
            'global_discount_amount' => 0,
            'total_amount' => 500,
        ]);

        $originalItem = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 100,
            'subtotal' => 500,
            'line_total_discount' => 0,
            'line_total' => 500,
        ]);

        $return = SaleReturnInvoice::factory()->create([
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => SaleReturnStatus::Draft,
        ]);

        $returnItem = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();

        $this->assertEquals(200, $return->items_refund_total);
        $this->assertEquals(200, $return->total_refund_amount);

        $returnItem->refresh();
        $this->assertEquals(0, $returnItem->prorated_global_discount);
        $this->assertEquals(100, $returnItem->effective_unit_refund);
        $this->assertEquals(200, $returnItem->item_refund_total);
    }

    /**
     * test_recalculate_return_totals_with_item_level_discount
     */
    public function test_recalculate_return_totals_with_item_level_discount()
    {
        $variant = $this->createVariantWithStock();

        $invoice = SaleInvoice::factory()->create([
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
            'subtotal' => 500,
            'global_discount_amount' => 0,
            'total_amount' => 450,
        ]);

        $originalItem = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 100,
            'subtotal' => 500,
            'discount_type' => DiscountType::Fixed,
            'unit_discount_amount' => 10,
            'line_total_discount' => 50,
            'line_total' => 450,
        ]);

        $return = SaleReturnInvoice::factory()->create([
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => SaleReturnStatus::Draft,
        ]);

        $returnItem = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 100,
            'unit_discount_amount' => 10,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();
        $returnItem->refresh();

        // Effective unit refund = 100 - 10 = 90
        $this->assertEquals(90, $returnItem->effective_unit_refund);
        $this->assertEquals(180, $returnItem->item_refund_total);
        $this->assertEquals(180, $return->total_refund_amount);
    }

    /**
     * test_recalculate_return_totals_with_global_fixed_discount
     */
    public function test_recalculate_return_totals_with_global_fixed_discount()
    {
        $variant = $this->createVariantWithStock();

        $invoice = SaleInvoice::factory()->create([
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
            'subtotal' => 1000,
            'discount_type' => DiscountType::Fixed,
            'discount_amount' => 100,
            'global_discount_amount' => 100,
            'total_amount' => 900,
        ]);

        $originalItem1 = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 100, // Subtotal: 500 (50% weight)
            'subtotal' => 500,
            'line_total_discount' => 0,
        ]);

        $originalItem2 = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'unit_price' => 50, // Subtotal: 500 (50% weight)
            'subtotal' => 500,
            'line_total_discount' => 0,
        ]);

        $return = SaleReturnInvoice::factory()->create([
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => SaleReturnStatus::Draft,
        ]);

        // Returning 2 units of Item 1
        $returnItem1 = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'original_item_id' => $originalItem1->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();
        $returnItem1->refresh();

        // Prorated discount: Item 1 has 50% weight, so it absorbs 50 EGP of global discount.
        // That 50 EGP is spread across its 5 units = 10 EGP per unit.
        // Unit refund = 100 - 10 = 90
        $this->assertEquals(50, $returnItem1->prorated_global_discount);
        $this->assertEquals(90, $returnItem1->effective_unit_refund);
        $this->assertEquals(180, $returnItem1->item_refund_total);
        $this->assertEquals(180, $return->total_refund_amount);
    }

    /**
     * test_finalize_return_restocks_inventory
     */
    public function test_finalize_return_restocks_inventory()
    {
        $variant = $this->createVariantWithStock(10); // 10 units in stock

        $invoice = SaleInvoice::factory()->create([
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);

        $originalItem = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 100,
        ]);

        $return = SaleReturnInvoice::factory()->create([
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => SaleReturnStatus::Draft,
        ]);

        SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'unit_price' => 100,
        ]);

        SaleInvoiceService::make()->finalizeReturn($return);

        $variant->refresh();
        $this->assertEquals(13, $variant->quantity); // Restocked 3 units

        // Verify movement
        $this->assertDatabaseHas('inventory_movements', [
            'variant_id' => $variant->id,
            'type' => MovementType::SaleReturn->value,
            'quantity' => 3,
        ]);

        $return->refresh();
        $this->assertEquals(SaleReturnStatus::Finalized, $return->status);
        $this->assertNotNull($return->finalized_at);

        $invoice->refresh();
        $this->assertEquals(SaleInvoiceReturnStatus::PartiallyReturned, $invoice->return_status);
    }

    /**
     * test_finalize_fails_when_quantity_exceeds_returnable
     */
    public function test_finalize_fails_when_quantity_exceeds_returnable()
    {
        $variant = $this->createVariantWithStock();

        $invoice = SaleInvoice::factory()->create([
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);

        $originalItem = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 100,
        ]);

        $return = SaleReturnInvoice::factory()->create([
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => SaleReturnStatus::Draft,
        ]);

        // Attempting to return 6 units, but original only had 5
        SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $variant->id,
            'quantity' => 6,
            'unit_price' => 100,
        ]);

        $this->expectException(\RuntimeException::class);
        SaleInvoiceService::make()->finalizeReturn($return);
    }
}
