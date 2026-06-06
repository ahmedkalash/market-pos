<?php

namespace Tests\Feature\Services;

use App\Enums\ExtraItemActionType;
use App\Models\SaleReturnExtraItem;
use App\Models\SaleReturnInvoice;
use App\Models\SaleReturnInvoiceItem;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateReturnTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_single_item_basic_aggregation()
    {
        $return = SaleReturnInvoice::factory()->create();

        $item = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 3,
            'effective_unit_refund' => 50.00,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();
        $item->refresh();

        $this->assertEquals(150.00, $item->item_refund_total);
        $this->assertEquals(150.00, $return->items_refund_total);
        $this->assertEquals(0, $return->extra_items_total);
        $this->assertEquals(150.00, $return->total_refund_amount);
    }

    public function test_user_override_of_effective_unit_refund_is_preserved()
    {
        $return = SaleReturnInvoice::factory()->create();

        // Simulating the case where effective unit refund was overridden by a user
        $item = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 2,
            'effective_unit_refund' => 75.00, // Used to be 90, but user changed to 75
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $item->refresh();

        // Must still be 75.00
        $this->assertEquals(75.00, $item->effective_unit_refund);
        $this->assertEquals(150.00, $item->item_refund_total);
    }

    public function test_multiple_items_aggregation()
    {
        $return = SaleReturnInvoice::factory()->create();

        $item1 = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 2,
            'effective_unit_refund' => 20.00,
        ]);

        $item2 = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 5,
            'effective_unit_refund' => 10.00,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();
        $item1->refresh();
        $item2->refresh();

        $this->assertEquals(40.00, $item1->item_refund_total);
        $this->assertEquals(50.00, $item2->item_refund_total);
        $this->assertEquals(90.00, $return->items_refund_total);
        $this->assertEquals(90.00, $return->total_refund_amount);
    }

    public function test_extra_items_addition_type()
    {
        $return = SaleReturnInvoice::factory()->create();

        SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 2,
            'effective_unit_refund' => 50.00, // Items refund = 100
        ]);

        SaleReturnExtraItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'action_type' => ExtraItemActionType::Addition,
            'amount' => 25.00,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();

        $this->assertEquals(100.00, $return->items_refund_total);
        $this->assertEquals(25.00, $return->extra_items_total);
        $this->assertEquals(125.00, $return->total_refund_amount);
    }

    public function test_extra_items_subtraction_type()
    {
        $return = SaleReturnInvoice::factory()->create();

        SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 2,
            'effective_unit_refund' => 50.00, // Items refund = 100
        ]);

        SaleReturnExtraItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'action_type' => ExtraItemActionType::Subtraction,
            'amount' => 30.00,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();

        $this->assertEquals(100.00, $return->items_refund_total);
        $this->assertEquals(-30.00, $return->extra_items_total);
        $this->assertEquals(70.00, $return->total_refund_amount);
    }

    public function test_extra_items_mixed_types()
    {
        $return = SaleReturnInvoice::factory()->create();

        SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 4,
            'effective_unit_refund' => 50.00, // Items refund = 200
        ]);

        SaleReturnExtraItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'action_type' => ExtraItemActionType::Addition,
            'amount' => 50.00,
        ]);

        SaleReturnExtraItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'action_type' => ExtraItemActionType::Subtraction,
            'amount' => 30.00,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();

        $this->assertEquals(200.00, $return->items_refund_total);
        $this->assertEquals(20.00, $return->extra_items_total); // 50 - 30
        $this->assertEquals(220.00, $return->total_refund_amount); // 200 + 20
    }

    public function test_does_not_overwrite_unit_price()
    {
        $return = SaleReturnInvoice::factory()->create();

        $item = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'unit_price' => 100.00,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $item->refresh();

        $this->assertEquals(100.00, $item->unit_price);
    }

    public function test_does_not_overwrite_unit_discount_amount()
    {
        $return = SaleReturnInvoice::factory()->create();

        $item = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'unit_discount_amount' => 10.00,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $item->refresh();

        $this->assertEquals(10.00, $item->unit_discount_amount);
    }

    public function test_does_not_overwrite_prorated_global_discount()
    {
        $return = SaleReturnInvoice::factory()->create();

        $item = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'unit_prorated_global_discount' => 5.5000,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $item->refresh();

        $this->assertEquals(5.5000, $item->unit_prorated_global_discount);
    }

    public function test_does_not_overwrite_effective_unit_refund()
    {
        $return = SaleReturnInvoice::factory()->create();

        $item = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'effective_unit_refund' => 42.1234,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $item->refresh();

        $this->assertEquals(42.1234, $item->effective_unit_refund);
    }

    public function test_decimal_precision_rounding()
    {
        $return = SaleReturnInvoice::factory()->create();

        $item = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 3,
            'effective_unit_refund' => 33.3333,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();
        $item->refresh();

        // 3 * 33.3333 = 99.9999, rounded to 2 decimal places = 100.00
        $this->assertEquals(100.00, $item->item_refund_total);
        $this->assertEquals(100.00, $return->items_refund_total);
        $this->assertEquals(100.00, $return->total_refund_amount);
    }

    public function test_zero_effective_unit_refund_edge_case()
    {
        $return = SaleReturnInvoice::factory()->create();

        $item = SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return->id,
            'quantity' => 5,
            'effective_unit_refund' => 0.00,
        ]);

        SaleInvoiceService::make()->recalculateReturnTotals($return);
        $return->refresh();
        $item->refresh();

        $this->assertEquals(0.00, $item->item_refund_total);
        $this->assertEquals(0.00, $return->items_refund_total);
        $this->assertEquals(0.00, $return->total_refund_amount);
    }
}
