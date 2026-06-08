<?php

namespace Tests\Feature\Services;

use App\Enums\ExtraItemActionType;
use App\Enums\PriceType;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceExtraItem;
use App\Models\SaleInvoiceItem;
use App\Models\Store;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateSaleInvoiceTotalsWithExtrasTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->store = Store::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_it_recalculates_totals_with_additions_and_subtractions()
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'shipping_cost' => 10.00,
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'retail_price' => 100,
        ]);

        // Add 2 items, 100 each. Subtotal: 200.
        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'price_type' => PriceType::Retail,
            'quantity' => 2,
            'unit_price' => 100,
            'line_total_discount' => 0,
        ]);

        // Subtotal = 200, Shipping = 10.
        // Add an extra item + 50 (Delivery fee)
        SaleInvoiceExtraItem::create([
            'sale_invoice_id' => $invoice->id,
            'name' => 'Delivery Fee',
            'action_type' => ExtraItemActionType::Addition,
            'amount' => 50.00,
        ]);

        // Add an extra item - 10 (Loyalty points)
        SaleInvoiceExtraItem::create([
            'sale_invoice_id' => $invoice->id,
            'name' => 'Loyalty Discount',
            'action_type' => ExtraItemActionType::Subtraction,
            'amount' => 10.00,
        ]);

        SaleInvoiceService::make()->recalculateTotals($invoice);

        $invoice->refresh();

        $this->assertEquals(200.00, $invoice->subtotal);
        $this->assertEquals(40.00, $invoice->extra_items_total); // 50 - 10
        // Total Amount = 200 (subtotal) + 40 (extra) + 10 (shipping) = 250
        $this->assertEquals(250.00, $invoice->total_amount);
    }

    public function test_it_allows_finalization_with_only_extra_items()
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'shipping_cost' => 0,
        ]);

        // Add an extra item + 100 (Service fee without products)
        SaleInvoiceExtraItem::create([
            'sale_invoice_id' => $invoice->id,
            'name' => 'Consulting Fee',
            'action_type' => ExtraItemActionType::Addition,
            'amount' => 100.00,
        ]);

        SaleInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        $this->assertEquals(100.00, $invoice->extra_items_total);
        $this->assertEquals(100.00, $invoice->total_amount);

        // Should not throw an exception about empty items because there is an extra item
        SaleInvoiceService::make()->finalize($invoice);

        $this->assertTrue($invoice->refresh()->isFinalized());
    }

    public function test_it_prevents_finalization_with_no_items_and_no_extras()
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'shipping_cost' => 0,
        ]);

        SaleInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('sale_invoice.no_items_or_extras'));

        SaleInvoiceService::make()->finalize($invoice);
    }
}
