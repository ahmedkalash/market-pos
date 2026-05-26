<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\PriceType;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleInvoiceDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private ProductVariant $variantNegotiable;

    private ProductVariant $variantNonNegotiable;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->store = Store::factory()->create(['company_id' => $company->id]);
        $this->user = User::factory()->create(['company_id' => $company->id, 'store_id' => $this->store->id]);

        $product = Product::factory()->create(['store_id' => $this->store->id]);

        $this->variantNegotiable = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'retail_price' => 100,
            'retail_is_price_negotiable' => true,
            'min_retail_price' => 80,
        ]);

        $this->variantNonNegotiable = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'retail_price' => 50,
            'retail_is_price_negotiable' => false,
            'min_retail_price' => 50,
        ]);
    }

    public function test_it_applies_item_fixed_discount_successfully()
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
        ]);

        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variantNegotiable->id,
            'quantity' => 2,
            'unit_price' => 100,
            'price_type' => PriceType::Retail,
            'discount_type' => DiscountType::Fixed,
            'unit_discount_amount' => 10,
        ]);

        SaleInvoiceService::make()->recalculateTotals($invoice);

        $invoice->refresh();
        $item = $invoice->items()->first();

        $this->assertEquals(200, $item->subtotal);
        $this->assertEquals(20, $item->line_total_discount);
        $this->assertEquals(180, $item->line_total);
        $this->assertEquals(200, $invoice->subtotal);
        $this->assertEquals(180, $invoice->total_amount);
        $this->assertEquals(0, $invoice->global_discount_amount);
    }

    public function test_it_applies_item_percentage_discount_successfully()
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
        ]);

        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variantNegotiable->id,
            'quantity' => 2,
            'unit_price' => 100,
            'price_type' => PriceType::Retail,
            'discount_type' => DiscountType::Percentage,
            'unit_discount_amount' => 10,
        ]);

        SaleInvoiceService::make()->recalculateTotals($invoice);

        $invoice->refresh();
        $item = $invoice->items()->first();

        $this->assertEquals(200, $item->subtotal);
        $this->assertEquals(20, $item->line_total_discount);
        $this->assertEquals(180, $item->line_total);
        $this->assertEquals(200, $invoice->subtotal);
    }

    public function test_it_throws_if_item_discount_breaches_minimum()
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
        ]);

        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variantNegotiable->id,
            'quantity' => 1,
            'unit_price' => 100,
            'price_type' => PriceType::Retail,
            'discount_type' => DiscountType::Fixed,
            'unit_discount_amount' => 25,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('sale_invoice.item_below_minimum', ['item' => $this->variantNegotiable->name, 'min' => 80]));

        SaleInvoiceService::make()->recalculateTotals($invoice);
    }

    public function test_it_throws_if_non_negotiable_item_receives_discount()
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
        ]);

        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variantNonNegotiable->id,
            'quantity' => 1,
            'unit_price' => 50,
            'price_type' => PriceType::Retail,
            'discount_type' => DiscountType::Fixed,
            'unit_discount_amount' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('sale_invoice.item_not_negotiable', ['item' => $this->variantNonNegotiable->name]));

        SaleInvoiceService::make()->recalculateTotals($invoice);
    }

    public function test_it_applies_and_distributes_invoice_level_discount()
    {
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
            'discount_type' => DiscountType::Percentage,
            'discount_amount' => 10,
        ]);

        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variantNegotiable->id,
            'quantity' => 2,
            'unit_price' => 100,
            'price_type' => PriceType::Retail,
        ]);

        $variantNegotiable2 = ProductVariant::factory()->create([
            'product_id' => $this->variantNegotiable->product_id,
            'retail_price' => 50,
            'retail_is_price_negotiable' => true,
            'min_retail_price' => 30,
        ]);

        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variantNegotiable2->id,
            'quantity' => 2,
            'unit_price' => 50,
            'price_type' => PriceType::Retail,
        ]);

        SaleInvoiceService::make()->recalculateTotals($invoice);

        $invoice->refresh();
        $this->assertEquals(300, $invoice->subtotal);
        $this->assertEquals(270, $invoice->total_amount);
        $this->assertEquals(30, $invoice->global_discount_amount);

        $items = $invoice->items()->orderBy('unit_price')->get();
        $this->assertEquals(100, $items[0]->subtotal);
        $this->assertEquals(0, $items[0]->line_total_discount);
        $this->assertEquals(100, $items[0]->line_total);

        $this->assertEquals(200, $items[1]->subtotal);
        $this->assertEquals(200, $items[1]->line_total);
    }

    public function test_it_calculates_grand_total_discount_correctly()
    {
        // Global Invoice Discount + Item level discounts combined
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->user->company_id,
            'store_id' => $this->store->id,
            'discount_type' => DiscountType::Fixed,
            'discount_amount' => 50, // 50 total invoice discount
        ]);

        // Item 1: No item discount
        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variantNegotiable->id,
            'quantity' => 2,
            'unit_price' => 100, // Subtotal: 200
            'price_type' => PriceType::Retail,
        ]);

        // Item 2: Fixed item discount
        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variantNegotiable->id,
            'quantity' => 3,
            'unit_price' => 100, // Subtotal: 300
            'price_type' => PriceType::Retail,
            'discount_type' => DiscountType::Fixed,
            'unit_discount_amount' => 10, // Total item discount: 30
        ]);

        // Item 3: Percentage item discount
        SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variantNegotiable->id,
            'quantity' => 1,
            'unit_price' => 200, // Subtotal: 200
            'price_type' => PriceType::Retail,
            'discount_type' => DiscountType::Percentage,
            'unit_discount_amount' => 10, // 10% of 200 = 20
        ]);

        // Total Initial Subtotals sum before any discounts = 200 + 300 + 200 = 700
        // Item Discounts Sum = 0 + 30 + 20 = 50
        // Total Subtotals after item discounts = 700 - 50 = 650
        // Global Invoice Discount = 50 (Fixed)
        // Grand Total Discount = Item Discounts Sum (50) + Global Invoice Discount (50) = 100
        // Total Amount = 650 - 50 = 600

        SaleInvoiceService::make()->recalculateTotals($invoice);

        $invoice->refresh();

        $this->assertEquals(600, $invoice->subtotal);
        $this->assertEquals(510, $invoice->total_amount);
        $this->assertEquals(50, $invoice->global_discount_amount);
        $this->assertEquals(90, $invoice->grand_total_discount);
    }
}
