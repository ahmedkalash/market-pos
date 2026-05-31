<?php

namespace Tests\Feature\Services;

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
use RuntimeException;
use Tests\TestCase;

class SaleReturnServiceConcurrencyTest extends TestCase
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

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'retail_price' => $price,
            'quantity' => $qty,
        ]);
    }

    /**
     * test_multiple_partial_returns_against_same_invoice
     */
    public function test_multiple_partial_returns_against_same_invoice()
    {
        $variant = $this->createVariantWithStock(100);

        $invoice = SaleInvoice::factory()->create([
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
        ]);

        $originalItem = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'unit_price' => 100,
        ]);

        // First return: 3 units
        $return1 = SaleReturnInvoice::factory()->create([
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => SaleReturnStatus::Draft,
        ]);
        SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return1->id,
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'unit_price' => 100,
        ]);
        SaleInvoiceService::make()->finalizeReturn($return1);
        $this->assertEquals(7, $originalItem->getRemainingReturnableQuantity());

        // Second return: 5 units
        $return2 = SaleReturnInvoice::factory()->create([
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => SaleReturnStatus::Draft,
        ]);
        SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return2->id,
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_price' => 100,
        ]);
        SaleInvoiceService::make()->finalizeReturn($return2);
        $this->assertEquals(2, $originalItem->getRemainingReturnableQuantity());

        // Third return: 5 units (Should Fail)
        $return3 = SaleReturnInvoice::factory()->create([
            'store_id' => $this->store->id,
            'original_invoice_id' => $invoice->id,
            'status' => SaleReturnStatus::Draft,
        ]);
        SaleReturnInvoiceItem::factory()->create([
            'sale_return_invoice_id' => $return3->id,
            'original_item_id' => $originalItem->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5, // Exceeds remaining 2
            'unit_price' => 100,
        ]);

        $this->expectException(RuntimeException::class);
        SaleInvoiceService::make()->finalizeReturn($return3);
    }
}
