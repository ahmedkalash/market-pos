<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\ExtraItemActionType;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\SaleInvoiceStatus;
use App\Enums\SequenceType;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceExtraItem;
use App\Models\SaleInvoiceItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        /** @var User|null $user */
        $user = auth()->user();

        $storeId = $metaData['store_id']
            ?? $user?->store_id
            ?? $user?->company?->stores()->first()?->id
            ?? ($user ? Store::where('company_id', $user->company_id)->first()?->id : null);

        $companyId = $metaData['company_id']
            ?? $user?->company_id
            ?? ($storeId ? Store::find($storeId)?->company_id : null);

        $globalDiscountAmount = (float) ($metaData['global_discount_amount'] ?? $metaData['global_discount'] ?? 0);
        $globalDiscountType = DiscountType::tryFrom($metaData['global_discount_type'] ?? '')
            ?: ($globalDiscountAmount > 0 ? DiscountType::Fixed : null);

        $invoice = SaleInvoice::create([
            'company_id' => $companyId,
            'store_id' => $storeId, // Ensures POS sales are linked to the cashier's store
            'customer_id' => $metaData['customer_id'] ?? null,
            'invoice_number' => SequenceService::make()->next($companyId, SequenceType::SaleInvoice),
            'status' => SaleInvoiceStatus::Draft,
            'payment_method' => $metaData['payment_method'] ?? PaymentMethod::Cash,
            'discount_type' => $globalDiscountType,
            'discount_amount' => $globalDiscountAmount,
            'shipping_destination_id' => $metaData['shipping_destination_id'] ?? null,
            'shipping_cost' => $metaData['shipping_cost'] ?? 0,
            'shipping_address' => $metaData['shipping_address'] ?? null,
            'created_by' => $user?->id,
            'notes' => 'Created via POS Terminal',
        ]);

        $variantIds = collect($cartData)->pluck('variant_id')->unique()->toArray();
        $variants = ProductVariant::whereIn('id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($cartData as $item) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($item['variant_id']);
            if (! $variant) {
                throw ValidationException::withMessages([
                    'cart' => __('sale_invoice.product_not_found'),
                ]);
            }

            $quantity = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
            $priceType = PriceType::tryFrom($item['price_type'] ?? '') ?? PriceType::Retail;

            // 1. Stock availability validation
            if ($quantity > (float) $variant->quantity) {
                throw ValidationException::withMessages([
                    'cart' => __('pos.insufficient_stock_error', [
                        'product' => $variant->full_qualified_name,
                        'available' => (float) $variant->quantity,
                    ]),
                ]);
            }

            // 2. Wholesale minimum quantity threshold validation
            if ($priceType === PriceType::Wholesale && $variant->hasWholesaleQtyThreshold() && $quantity < (float) $variant->wholesale_qty_threshold) {
                throw ValidationException::withMessages([
                    'cart' => __('pos.wholesale_min_qty_error', [
                        'product' => $variant->full_qualified_name,
                        'min' => (float) $variant->wholesale_qty_threshold,
                    ]),
                ]);
            }

            // 3. Discount eligibility validation
            $itemDiscountAmount = (float) ($item['discount_amount'] ?? $item['discount'] ?? 0);
            $itemDiscountType = DiscountType::tryFrom($item['discount_type'] ?? '')
                ?: ($itemDiscountAmount > 0 ? DiscountType::Fixed : null);

            if ($itemDiscountAmount > 0 && ! $variant->isPriceNegotiable($priceType)) {
                throw ValidationException::withMessages([
                    'cart' => __('sale_invoice.item_not_negotiable', [
                        'item' => $variant->name() ?? $variant->full_qualified_name,
                    ]),
                ]);
            }

            SaleInvoiceItem::create([
                'sale_invoice_id' => $invoice->id,
                'product_variant_id' => $variant->id,
                'price_type' => $priceType,
                'unit_price' => $item['unit_price'] ?? $item['price'] ?? 0, // Gets overwritten securely by recalculateTotals
                'quantity' => $quantity,
                'discount_type' => $itemDiscountType,
                'unit_discount_amount' => $itemDiscountAmount,
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
