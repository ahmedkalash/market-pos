<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\ExtraItemActionType;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\SaleInvoiceStatus;
use App\Enums\SequenceType;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceExtraItem;
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
            'discount_type' => DiscountType::tryFrom($metaData['global_discount_type'] ?? '') ?: null,
            'discount_amount' => $metaData['global_discount_amount'] ?? 0,
            'shipping_destination_id' => $metaData['shipping_destination_id'] ?? null,
            'shipping_cost' => $metaData['shipping_cost'] ?? 0,
            'shipping_address' => $metaData['shipping_address'] ?? null,
            'created_by' => $user->id,
            'notes' => 'Created via POS Terminal',
        ]);

        foreach ($cartData as $item) {
            SaleInvoiceItem::create([
                'sale_invoice_id' => $invoice->id,
                'product_variant_id' => $item['variant_id'],
                'price_type' => PriceType::tryFrom($item['price_type'] ?? '') ?? PriceType::Retail,
                'unit_price' => $item['unit_price'] ?? $item['price'] ?? 0, // Gets overwritten securely by recalculateTotals
                'quantity' => $item['quantity'] ?? $item['qty'] ?? 1,
                'discount_type' => DiscountType::tryFrom($item['discount_type'] ?? '') ?: null,
                'unit_discount_amount' => (float) ($item['discount_amount'] ?? 0),
                'subtotal' => 0, // Recalculated securely
                'tax_rate' => 0,
                'tax_amount' => 0,
                'line_total' => 0,
            ]);
        }

        foreach ($metaData['extra_items'] ?? [] as $extraItem) {
            SaleInvoiceExtraItem::create([
                'sale_invoice_id' => $invoice->id,
                'name' => $extraItem['name'],
                'action_type' => ExtraItemActionType::tryFrom($extraItem['action_type'] ?? '') ?? ExtraItemActionType::Addition,
                'amount' => (float) ($extraItem['amount'] ?? 0),
                'notes' => $extraItem['notes'] ?? null,
            ]);
        }

        return $invoice;
    }
}
