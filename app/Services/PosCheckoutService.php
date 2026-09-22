<?php

namespace App\Services;

use App\DTOs\Checkout\CartItemDTO;
use App\DTOs\Checkout\CheckoutMetaDataDTO;
use App\Enums\SaleInvoiceStatus;
use App\Enums\SequenceType;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceExtraItem;
use App\Models\SaleInvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Coordinates the full checkout and holding lifecycles for the POS Terminal.
 *
 * Serves as the primary transaction orchestrator for cashier operations,
 * creating draft sales documents and delegating financial recalculations
 * and inventory movements to SaleInvoiceService.
 */
// todo: review
class PosCheckoutService
{
    public static function make(): self
    {
        return app(static::class);
    }

    /**
     * Finalize POS checkout: atomically create invoice, calculate totals, deduct stock, and lock as Finalized.
     *
     * ### Transaction Boundary: Self-Contained (Boundary: `self`)
     * - **Manages Transaction:** Yes (`DB::transaction`). Callers do not need to wrap this in a transaction.
     * - **Nesting:** If invoked within an outer transaction, it seamlessly participates in that transaction.
     * - **Rollback:** Any validation failure (e.g. stock deficit, wholesale threshold, discount violation)
     *   triggers an immediate rollback, leaving zero uncommitted or orphan records in the database.
     * - **Orchestration:** Atomically wraps:
     *   1. `createDraftInvoice()` (draft invoice header, lines, and extra items)
     *   2. `SaleInvoiceService::recalculateTotals()` (prices, discounts, line totals)
     *   3. `SaleInvoiceService::finalize()` (inventory stock deduction & finalized status)
     *
     * @param  array<CartItemDTO>|array<array<string, mixed>>  $cartData
     * @param  CheckoutMetaDataDTO|array<string, mixed>  $metaData
     * @return SaleInvoice The freshly reloaded, finalized SaleInvoice.
     *
     * @throws ValidationException If items are missing or available stock is insufficient.
     * @throws \RuntimeException If wholesale threshold or discount rules are violated.
     * @throws Throwable
     */
    public function checkout(array $cartData, CheckoutMetaDataDTO|array $metaData): SaleInvoice
    {
        $cartItems = array_map(
            fn ($item): CartItemDTO => $item instanceof CartItemDTO ? $item : CartItemDTO::fromArray($item),
            $cartData
        );
        $meta = $metaData instanceof CheckoutMetaDataDTO ? $metaData : CheckoutMetaDataDTO::fromArray($metaData);

        return DB::transaction(function () use ($cartItems, $meta) {
            $invoice = $this->createDraftInvoice($cartItems, $meta);

            SaleInvoiceService::make()->recalculateTotals($invoice);
            SaleInvoiceService::make()->finalize($invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Put an active POS cart on hold by creating a draft sale invoice without deducting stock.
     *
     * ### Transaction Boundary: Self-Contained (Boundary: `self`)
     * - **Manages Transaction:** Yes (`DB::transaction`).
     * - **Rollback:** Any calculation or line item failure automatically rolls back the entire draft creation.
     * - **Stock Impact:** None. Unlike checkout, stock is NOT deducted; invoice remains in Draft status.
     *
     * @param  array<CartItemDTO>|array<array<string, mixed>>  $cartData
     * @param  CheckoutMetaDataDTO|array<string, mixed>  $metaData
     * @return SaleInvoice The freshly reloaded draft SaleInvoice.
     *
     * @throws ValidationException If items are missing or cart data is invalid.
     * @throws \RuntimeException If line calculations breach business rules.
     * @throws Throwable
     */
    public function holdCart(array $cartData, CheckoutMetaDataDTO|array $metaData): SaleInvoice
    {
        $cartItems = array_map(
            fn ($item): CartItemDTO => $item instanceof CartItemDTO ? $item : CartItemDTO::fromArray($item),
            $cartData
        );
        $meta = $metaData instanceof CheckoutMetaDataDTO ? $metaData : CheckoutMetaDataDTO::fromArray($metaData);

        return DB::transaction(function () use ($cartItems, $meta) {
            $invoice = $this->createDraftInvoice($cartItems, $meta);

            SaleInvoiceService::make()->recalculateTotals($invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Create the draft sale invoice header along with its line items and extra charges.
     *
     * ### Transaction Boundary: Required Outer Transaction (Boundary: `required`)
     * - **Manages Transaction:** No. This is an internal helper that MUST execute inside an active
     *   database transaction (provided by `checkout()` or `holdCart()`).
     * - **Concurrency:** Acquires a pessimistic lock (`lockForUpdate()`) on all purchased product variants
     *   to prevent concurrent stock and price changes during draft assembly.
     *
     * @param  array<CartItemDTO>  $cartItems
     * @return SaleInvoice The newly created draft invoice.
     *
     * @throws ValidationException
     */
    private function createDraftInvoice(array $cartItems, CheckoutMetaDataDTO $metaData): SaleInvoice
    {
        /** @var User|null $user */
        $user = auth()->user();

        $storeId = $metaData->storeId;
        $companyId = $metaData->companyId;

        $invoice = SaleInvoice::create([
            'company_id' => $companyId,
            'store_id' => $storeId, // Ensures POS sales are linked to the cashier's store
            'customer_id' => $metaData->customerId,
            'invoice_number' => SequenceService::make()->next($companyId, SequenceType::SaleInvoice),
            'status' => SaleInvoiceStatus::Draft,
            'payment_method' => $metaData->paymentMethod,
            'discount_type' => $metaData->globalDiscountType,
            'discount_amount' => $metaData->globalDiscountAmount,
            'shipping_destination_id' => $metaData->shippingDestinationId,
            'shipping_cost' => $metaData->shippingCost ?? 0.0,
            'shipping_address' => $metaData->shippingAddress,
            'created_by' => $user->id,
            'notes' => __('pos.created_via_terminal'),
        ]);

        $variantIds = array_unique(array_map(fn (CartItemDTO $item): int => $item->variantId, $cartItems));
        $variants = ProductVariant::whereIn('id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($cartItems as $item) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($item->variantId);
            if (! $variant) {
                throw ValidationException::withMessages([
                    'cart' => __('sale_invoice.product_not_found'),
                ]);
            }

            $quantity = $item->quantity;
            $priceType = $item->priceType;

            // 1. Stock availability validation
            if ($quantity > (float) $variant->quantity) {
                throw ValidationException::withMessages([
                    'cart' => __('pos.insufficient_stock_error', [
                        'product' => $variant->full_qualified_name,
                        'available' => (float) $variant->quantity,
                    ]),
                ]);
            }

            SaleInvoiceItem::create([
                'sale_invoice_id' => $invoice->id,
                'product_variant_id' => $variant->id,
                'price_type' => $priceType,
                'unit_price' => $item->unitPrice, // Gets overwritten securely by recalculateTotals
                'quantity' => $quantity,
                'discount_type' => $item->discountType,
                'unit_discount_amount' => $item->discountAmount,
                'subtotal' => 0, // Recalculated securely
                'tax_rate' => 0,
                'tax_amount' => 0,
                'line_total' => 0,
            ]);
        }

        foreach ($metaData->extraItems as $extraItem) {
            SaleInvoiceExtraItem::create([
                'sale_invoice_id' => $invoice->id,
                'name' => $extraItem->name,
                'action_type' => $extraItem->actionType,
                'amount' => $extraItem->amount,
                'notes' => $extraItem->notes,
            ]);
        }

        return $invoice;
    }
}
