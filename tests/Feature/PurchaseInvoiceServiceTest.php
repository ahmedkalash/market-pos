<?php

namespace Tests\Feature;

use App\Enums\ExtraItemActionType;
use App\Enums\InvoiceReturnStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceExtraItem;
use App\Models\PurchaseInvoiceItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Company $company;

    private User $user;

    private Vendor $vendor;

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
    }

    public function test_recalculate_totals_includes_extra_items(): void
    {
        $invoice = PurchaseInvoice::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'vendor_id' => $this->vendor->id,
            'status' => PurchaseInvoiceStatus::Draft,
            'return_status' => InvoiceReturnStatus::None,
            'vendor_invoice_ref' => '123',
            'invoice_number' => 'INV-123',
            'date' => now(),
            'due_date' => now(),
            'received_at' => now(),
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'unit_cost' => 100,
            'quantity' => 1,
            'tax_amount' => 10,
            'line_total' => 110,
        ]);

        // Add extra items
        PurchaseInvoiceExtraItem::create([
            'purchase_invoice_id' => $invoice->id,
            'action_type' => ExtraItemActionType::Addition,
            'amount' => 50,
            'name' => 'Fee',
        ]);

        PurchaseInvoiceExtraItem::create([
            'purchase_invoice_id' => $invoice->id,
            'action_type' => ExtraItemActionType::Subtraction,
            'amount' => 20,
            'name' => 'Discount',
        ]);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
        $invoice->refresh();

        $this->assertEquals(100, $invoice->total_before_tax);
        $this->assertEquals(0, $invoice->total_tax_amount); // MVP postponed
        $this->assertEquals(30, $invoice->extra_items_total); // 50 - 20
        $this->assertEquals(130, $invoice->total_amount); // 100 + 0 + 30
    }

    public function test_recalculate_totals_clamps_total_amount_to_zero(): void
    {
        $invoice = PurchaseInvoice::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'vendor_id' => $this->vendor->id,
            'status' => PurchaseInvoiceStatus::Draft,
            'return_status' => InvoiceReturnStatus::None,
            'vendor_invoice_ref' => '123',
            'invoice_number' => 'INV-456',
            'date' => now(),
            'due_date' => now(),
            'received_at' => now(),
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'unit_cost' => 10,
            'quantity' => 1,
            'tax_amount' => 0,
            'line_total' => 10,
        ]);

        // Add a massive subtraction
        PurchaseInvoiceExtraItem::create([
            'purchase_invoice_id' => $invoice->id,
            'action_type' => ExtraItemActionType::Subtraction,
            'amount' => 100,
            'name' => 'Discount',
        ]);

        PurchaseInvoiceService::make()->recalculateTotals($invoice);

        $invoice->refresh();

        $this->assertEquals(10, $invoice->total_before_tax);
        $this->assertEquals(0, $invoice->total_tax_amount);
        $this->assertEquals(-100, $invoice->extra_items_total);
        // Ensure total amount doesn't go below 0
        $this->assertEquals(0, $invoice->total_amount);
    }
}
