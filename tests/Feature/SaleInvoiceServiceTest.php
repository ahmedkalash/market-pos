<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SaleInvoiceStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaleInvoiceService $service;

    private User $user;

    private Store $store;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SaleInvoiceService::class);

        $company = Company::factory()->create(['is_active' => true]);
        $this->store = Store::factory()->create(['company_id' => $company->id]);

        $this->user = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
            'is_active' => true,
        ]);

        $category = ProductCategory::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
        ]);

        $taxClass = TaxClass::factory()->create([
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'tax_class_id' => $taxClass->id,
        ]);

        $uom = UnitOfMeasure::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
        ]);

        $this->variant = ProductVariant::factory()->withStock(100)->create([
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'retail_price' => 15.00,
        ]);

        $this->actingAs($this->user);
    }

    public function test_recalculate_totals_computes_correct_financials(): void
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Draft,
        ]);

        $item = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2.000,
            'unit_price' => 10.0000,
            'subtotal' => 0.00,
            'tax_rate' => 15.00, // input rate
            'tax_amount' => 0.00,
            'line_total' => 0.00,
        ]);

        $this->service->recalculateTotals($invoice);

        $item->refresh();
        $invoice->refresh();

        // Subtotal = 2 * 15.00 = 30.00. Tax rate is forced to 0 for MVP as per SaleInvoiceService.php.
        $this->assertEquals(30.00, (float) $item->subtotal);
        $this->assertEquals(0.00, (float) $item->tax_rate);
        $this->assertEquals(0.00, (float) $item->tax_amount);
        $this->assertEquals(30.00, (float) $item->line_total);

        $this->assertEquals(30.00, (float) $invoice->subtotal);
        $this->assertEquals(30.00, (float) $invoice->total_before_tax);
        $this->assertEquals(0.00, (float) $invoice->total_tax_amount);
        $this->assertEquals(30.00, (float) $invoice->total_amount);
    }

    public function test_finalize_deducts_stock_and_locks_invoice(): void
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Draft,
            'payment_method' => PaymentMethod::Cash,
        ]);

        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10.000,
            'unit_price' => 15.0000,
            'subtotal' => 150.00,
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'line_total' => 150.00,
        ]);

        $this->service->finalize($invoice);

        $invoice->refresh();
        $this->variant->refresh();

        // Verify status & payment method
        $this->assertEquals(SaleInvoiceStatus::Finalized, $invoice->status);
        $this->assertEquals(PaymentMethod::Cash, $invoice->payment_method);
        $this->assertNotNull($invoice->finalized_at);
        $this->assertEquals($this->user->id, $invoice->finalized_by);

        // Verify stock deducted
        $this->assertEquals(90.0, (float) $this->variant->quantity);

        // Verify movement recorded
        $movement = InventoryMovement::where([
            'reference_type' => SaleInvoice::class,
            'reference_id' => $invoice->id,
        ])->first();

        $this->assertNotNull($movement);
        $this->assertEquals(MovementType::Sale, $movement->type);
        $this->assertEquals(10.0, (float) $movement->quantity);
        $this->assertEquals($this->store->id, $movement->store_id);
    }

    public function test_finalize_fails_when_no_items(): void
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Draft,
            'payment_method' => PaymentMethod::Cash,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('sale_invoice.no_items_or_extras'));

        $this->service->finalize($invoice);
    }

    public function test_finalize_fails_when_insufficient_stock(): void
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Draft,
            'payment_method' => PaymentMethod::Cash,
        ]);

        // Attempting to sell 150.00 when stock is 100
        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 150.000,
            'unit_price' => 15.0000,
            'subtotal' => 2250.00,
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'line_total' => 2250.00,
        ]);

        $this->expectException(InsufficientStockException::class);

        try {
            $this->service->finalize($invoice);
        } finally {
            $invoice->refresh();
            $this->variant->refresh();

            // Verify invoice remains Draft
            $this->assertEquals(SaleInvoiceStatus::Draft, $invoice->status);
            $this->assertNull($invoice->finalized_at);

            // Verify stock is still 100 (rollback)
            $this->assertEquals(100.0, (float) $this->variant->quantity);
        }
    }

    public function test_finalize_fails_when_item_variant_belongs_to_different_store(): void
    {
        $otherStore = Store::factory()->create(['company_id' => $this->user->company_id]);

        $otherProduct = Product::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $otherStore->id,
        ]);

        $otherVariant = ProductVariant::factory()->withStock(100)->create([
            'product_id' => $otherProduct->id,
        ]);

        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Draft,
            'payment_method' => PaymentMethod::Cash,
        ]);

        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $otherVariant->id,
            'quantity' => 5.000,
            'unit_price' => 10.0000,
            'subtotal' => 50.00,
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'line_total' => 50.00,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Variant [{$otherVariant->id}] does not belong to store [{$this->store->id}].");

        $this->service->finalize($invoice);
    }
}
