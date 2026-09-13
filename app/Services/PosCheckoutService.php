<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\SaleInvoiceStatus;
use App\Enums\SequenceType;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class PosCheckoutService
{
    public static function make(): self
    {
        return app(static::class);
    }

    /**
     * @throws Throwable
     */
    public function checkout(array $cartData, array $metaData): SaleInvoice
    {
        return DB::transaction(function () use ($cartData, $metaData) {
            $invoice = $this->createDraftInvoice($cartData, $metaData);

            SaleInvoiceService::make()->recalculateTotals($invoice);
            SaleInvoiceService::make()->finalize($invoice);

            return $invoice;
        });
    }

    public function holdCart(array $cartData, array $metaData): SaleInvoice
    {
        return DB::transaction(function () use ($cartData, $metaData) {
            $invoice = $this->createDraftInvoice($cartData, $metaData);

            SaleInvoiceService::make()->recalculateTotals($invoice);

            return $invoice;
        });
    }

    private function createDraftInvoice(array $cartData, array $metaData): SaleInvoice
    {
        /** @var User $user */
        $user = auth()->user();

        $storeId = $metaData['store_id']
            ?? $user->store_id
            ?? $user->company?->stores()->first()?->id
            ?? Store::where('company_id', $user->company_id)->first()?->id;

        $invoice = SaleInvoice::create([
            'company_id' => $user->company_id,
            'store_id' => $storeId, // Ensures POS sales are linked to the cashier's store
            'customer_id' => $metaData['customer_id'] ?? null,
            'invoice_number' => SequenceService::make()->next($user->company_id, SequenceType::SaleInvoice),
            'status' => SaleInvoiceStatus::Draft,
            'payment_method' => $metaData['payment_method'] ?? PaymentMethod::Cash,
            'discount_type' => ($metaData['global_discount'] ?? 0) > 0 ? DiscountType::Fixed : null,
            'discount_amount' => $metaData['global_discount'] ?? 0,
            'shipping_cost' => $metaData['shipping_cost'] ?? 0,
            'created_by' => $user->id,
            'notes' => 'Created via POS Terminal',
        ]);

        foreach ($cartData as $item) {
            $discount = (float) ($item['discount'] ?? $item['unit_discount_amount'] ?? 0);

            SaleInvoiceItem::create([
                'sale_invoice_id' => $invoice->id,
                'product_variant_id' => $item['variant_id'],
                'price_type' => PriceType::Retail, // Force retail for POS standard
                'unit_price' => $item['unit_price'] ?? $item['price'] ?? 0, // Gets overwritten securely by recalculateTotals
                'quantity' => $item['quantity'] ?? $item['qty'] ?? 1,
                'discount_type' => $discount > 0 ? DiscountType::Fixed : null,
                'unit_discount_amount' => $discount,
                'subtotal' => 0, // Recalculated securely
                'tax_rate' => 0,
                'tax_amount' => 0,
                'line_total' => 0,
            ]);
        }

        return $invoice;
    }
}
