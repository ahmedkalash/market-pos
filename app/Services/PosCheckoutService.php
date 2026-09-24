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
            $invoice = $this->saveDraftInvoice($cartItems, $meta, $meta->draftInvoiceId);

            SaleInvoiceService::make()->recalculateTotals($invoice);
            SaleInvoiceService::make()->finalize($invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Put an active POS cart on hold by creating or updating a draft sale invoice without deducting stock.
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
            $invoice = $this->saveDraftInvoice($cartItems, $meta, $meta->draftInvoiceId);

            SaleInvoiceService::make()->recalculateTotals($invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Create or update a draft sale invoice header along with its line items and extra charges.
     *
     * ### Transaction Boundary: Required Outer Transaction (Boundary: `required`)
     * - **Manages Transaction:** No. This is an internal helper that MUST execute inside an active
     *   database transaction (provided by `checkout()` or `holdCart()`).
     * - **Concurrency:** Acquires a pessimistic lock (`lockForUpdate()`) on all purchased product variants
     *   to prevent concurrent stock and price changes during draft assembly.
     *
     * @param  array<CartItemDTO>  $cartItems
     * @return SaleInvoice The draft invoice.
     *
     * @throws ValidationException
     */
    private function saveDraftInvoice(array $cartItems, CheckoutMetaDataDTO $metaData, ?int $draftInvoiceId = null): SaleInvoice
    {
        /** @var User|null $user */
        $user = auth()->user();

        $storeId = $metaData->storeId;
        $companyId = $metaData->companyId;

        if ($draftInvoiceId) {
            /** @var SaleInvoice $invoice */
            $invoice = SaleInvoice::where('id', $draftInvoiceId)
                ->filterByCompany($companyId)
                ->filterByStore($storeId)
                ->draft()
                ->lockForUpdate()
                ->firstOrFail();

            $invoice->update([
                'customer_id' => $metaData->customerId,
                'hold_reference' => $metaData->holdReference,
                'payment_method' => $metaData->paymentMethod,
                'discount_type' => $metaData->globalDiscountType,
                'discount_amount' => $metaData->globalDiscountAmount,
                'shipping_destination_id' => $metaData->shippingDestinationId,
                'shipping_cost' => $metaData->shippingCost ?? 0.0,
                'shipping_address' => $metaData->shippingAddress,
            ]);

            $invoice->items()->delete();
            $invoice->extraItems()->delete();
        } else {
            $invoice = SaleInvoice::create([
                'company_id' => $companyId,
                'store_id' => $storeId, // Ensures POS sales are linked to the cashier's store
                'customer_id' => $metaData->customerId,
                'invoice_number' => SequenceService::make()->next($companyId, SequenceType::SaleInvoice),
                'hold_reference' => $metaData->holdReference,
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
        }

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

    /**
     * Retrieve held/draft invoices for a given store, optionally filtered by search query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHeldInvoices(int $storeId, ?string $search = null, int $limit = 50): array
    {
        $query = SaleInvoice::query()
            ->where('store_id', $storeId)
            ->draft();

        if (filled($search)) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('hold_reference', 'like', "%{$term}%")
                    ->orWhere('invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    })
                    ->orWhereHas('items.variant', function ($vq) use ($term) {
                        $vq->where('name_en', 'like', "%{$term}%")
                            ->orWhere('name_ar', 'like', "%{$term}%")
                            ->orWhereHas('product', function ($pq) use ($term) {
                                $pq->where('name_en', 'like', "%{$term}%")
                                    ->orWhere('name_ar', 'like', "%{$term}%");
                            });
                    });
            });
        }

        return $query
            ->with([
                'customer:id,name,phone',
                'createdBy:id,name',
                'items.variant.product:id,name_en,name_ar',
            ])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (SaleInvoice $invoice) {
                $itemsCount = $invoice->items->sum('quantity');
                $itemsPreview = $invoice->items
                    ->take(3)
                    ->map(fn ($item) => ($item->variant?->name() ?? $item->variant?->full_qualified_name ?? __('app.unknown_product')).' (x'.(float) $item->quantity.')')
                    ->implode(', ');

                if ($invoice->items->count() > 3) {
                    $itemsPreview .= ', +'.($invoice->items->count() - 3).' '.__('pos.more_items');
                }

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'hold_reference' => $invoice->hold_reference ?: null,
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $invoice->customer?->name ?? __('pos.walk_in'),
                    'customer_phone' => $invoice->customer?->phone,
                    'items_count' => (float) $itemsCount,
                    'items_preview' => $itemsPreview,
                    'total_amount' => (float) $invoice->total_amount,
                    'created_at_human' => $invoice->created_at?->diffForHumans() ?? '',
                    'created_at_formatted' => $invoice->created_at?->format('H:i - d/m/Y') ?? '',
                    'cashier_name' => $invoice->createdBy?->name ?? __('app.unknown'),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Fetch a held/draft invoice and format it for complete rehydration into the POS Alpine.js cart.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function getDraftInvoiceForRehydration(int $invoiceId, int $storeId): array
    {
        /** @var SaleInvoice $invoice */
        $invoice = SaleInvoice::where('id', $invoiceId)
            ->where('store_id', $storeId)
            ->draft()
            ->with([
                'customer',
                'shippingDestination',
                'extraItems',
                'items.variant.product.category',
                'items.variant.unitOfMeasure',
                'items.variant.barcodes',
            ])
            ->firstOrFail();

        $cartItems = [];
        $hasStockWarning = false;

        foreach ($invoice->items as $item) {
            $variant = $item->variant;
            if (! $variant) {
                continue;
            }

            $availableStock = (float) $variant->quantity;
            $requestedQty = (float) $item->quantity;
            $stockViolated = $requestedQty > $availableStock;

            if ($stockViolated) {
                $hasStockWarning = true;
            }

            $cartItems[] = [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'category_id' => $variant->product?->category_id,
                'name' => $variant->full_qualified_name,
                'retail_price' => (float) $variant->retail_price,
                'wholesale_price' => (float) $variant->wholesale_price,
                'wholesale_enabled' => (bool) $variant->wholesale_enabled,
                'retail_is_price_negotiable' => (bool) $variant->retail_is_price_negotiable,
                'min_retail_price' => (float) $variant->min_retail_price,
                'wholesale_is_price_negotiable' => (bool) $variant->wholesale_is_price_negotiable,
                'min_wholesale_price' => (float) $variant->min_wholesale_price,
                'wholesale_qty_threshold' => (float) $variant->wholesale_qty_threshold,
                'uom_name' => $variant->unitOfMeasure?->name ?? '',
                'stock' => $availableStock,
                'qty' => $requestedQty,
                'priceType' => $item->price_type->value,
                'discountType' => $item->discount_type?->value ?? 'fixed',
                'discountAmount' => (float) ($item->unit_discount_amount ?? 0),
                'stock_warning' => $stockViolated,
            ];
        }

        $extraItems = $invoice->extraItems->map(fn ($extra) => [
            'name' => $extra->name,
            'amount' => (float) $extra->amount,
            'action_type' => $extra->action_type->value,
            'notes' => $extra->notes,
        ])->toArray();

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'hold_reference' => $invoice->hold_reference ?: '',
            'customer_id' => $invoice->customer_id,
            'customer_name' => $invoice->customer?->name ?? __('pos.walk_in'),
            'payment_method' => $invoice->payment_method?->value ?? 'cash',
            'global_discount_type' => $invoice->discount_type?->value ?? 'fixed',
            'global_discount_amount' => (float) ($invoice->discount_amount ?? 0),
            'shipping_destination_id' => $invoice->shipping_destination_id,
            'shipping_cost' => (float) ($invoice->shipping_cost ?? 0),
            'shipping_address' => $invoice->shipping_address ?? '',
            'extra_items' => $extraItems,
            'cart_items' => $cartItems,
            'has_stock_warning' => $hasStockWarning,
        ];
    }

    /**
     * Discard (delete) an unneeded draft sale invoice and its cascaded relations.
     *
     * @throws Throwable
     */
    public function discardDraftInvoice(int $invoiceId, int $storeId): bool
    {
        return DB::transaction(function () use ($invoiceId, $storeId) {
            $invoice = SaleInvoice::where('id', $invoiceId)
                ->filterByStore($storeId)
                ->draft()
                ->lockForUpdate()
                ->firstOrFail();

            return (bool) $invoice->delete();
        });
    }
}
