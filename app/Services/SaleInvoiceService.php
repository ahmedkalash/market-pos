<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\SaleInvoiceReturnStatus;
use App\Enums\SaleInvoiceStatus;
use App\Enums\SaleReturnStatus;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnInvoice;
use App\Models\SaleReturnInvoiceItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Handles the full transactional lifecycle of Sale Invoices.
 *
 * Invoice finalization:
 *  1. Validates each line variant belongs to the invoice's store.
 *  2. Calls InventoryService::recordMovement() with MovementType::Sale to deduct stock.
 *  3. Saves computed financial data on each item row.
 *  4. Calculates and saves invoice totals.
 *  5. Locks the invoice as Finalized with payment method.
 *
 * All operations run in a single DB::transaction(). Any failure rolls back everything.
 */
class SaleInvoiceService
{
    public static function make(): self
    {
        return app(static::class);
    }

    /**
     * Authoritatively recalculates all financial totals for a persisted Sale Invoice.
     *
     * This method is designed to run AFTER Filament has saved both the parent invoice
     * and its line items to the database (via afterCreate/afterSave hooks). It:
     *
     *  1. Loads items with their variant → product → taxClass relationships.
     *  2. For each line item, computes subtotal, tax_rate, tax_amount, and line_total
     *     using the authoritative tax rate from the database (not the frontend).
     *  3. Aggregates all line subtotals and tax amounts into the invoice-level
     *     total_before_tax, total_tax_amount, and total_amount fields.
     *
     * @throws Throwable
     */
    public function recalculateTotals(SaleInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = $this->prepareInvoiceForCalculation($invoice);

            // Pass 1: Calculate individual item totals, validate minimums, and update line items
            [
                'subtotals_before_discount_sum' => $subtotalsBeforeDiscountSum,
                'subtotals_after_discount_sum' => $subtotalsAfterDiscountSum,
                'total_minimum_allowed' => $totalMinimumAllowed,
                'total_tax_amount' => $totalTaxAmount,
                'total_items_discount' => $totalItemsDiscount,
            ] = $this->processLineItems($invoice);

            // Determine the total global discount to apply to the invoice
            $globalInvoiceDiscount = $this->calculateGlobalDiscount($invoice, $subtotalsAfterDiscountSum);

            // The total before tax is the sum of all item subtotals minus the global discount
            $totalBeforeTax = $subtotalsAfterDiscountSum - $globalInvoiceDiscount;

            // Enforce that the global discount does not push the invoice total below the absolute minimum
            if (round($totalBeforeTax, 2) < round($totalMinimumAllowed, 2)) {
                throw new \RuntimeException(__('sale_invoice.invoice_discount_breaches_minimum_total'));
            }

            $grandTotalDiscount = $totalItemsDiscount + $globalInvoiceDiscount;

            $shippingCost = (float) ($invoice->shipping_cost ?? 0);
            $extraItemsTotal = $invoice->calculateExtraItemsTotal();
            $totalAmount = max(0, $totalBeforeTax + $totalTaxAmount + $shippingCost + $extraItemsTotal);

            // Persist the final invoice totals
            $this->updateInvoiceTotals($invoice, [
                'subtotal' => round($subtotalsBeforeDiscountSum, 2),
                'global_discount_amount' => round($globalInvoiceDiscount, 2),
                'grand_total_discount' => round($grandTotalDiscount, 2),
                'extra_items_total' => round($extraItemsTotal, 2),
                'total_before_tax' => round($totalBeforeTax, 2),
                'total_tax_amount' => round($totalTaxAmount, 2),
                'total_amount' => round($totalAmount, 2),
            ]);
        });
    }

    /**
     * Re-fetch the invoice and lock it for update to prevent concurrent modifications,
     * and eager load related models, locking items to ensure data consistency during calculation.
     */
    private function prepareInvoiceForCalculation(SaleInvoice $invoice): SaleInvoice
    {
        $lockedInvoice = SaleInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
        $lockedInvoice->load(['items' => fn ($query) => $query->lockForUpdate()]);
        $lockedInvoice->load('items.variant.product.taxClass');
        $lockedInvoice->load('extraItems');

        return $lockedInvoice;
    }

    /**
     * Pass 1: Line Item Calculation & Minimums
     */
    private function processLineItems(SaleInvoice $invoice): array
    {
        $subtotalsBeforeDiscountSum = 0.0;
        $subtotalsAfterDiscountSum = 0.0;
        $totalMinimumAllowed = 0.0;
        $totalTaxAmount = 0.0;
        $totalItemsDiscount = 0.0;

        foreach ($invoice->items as $item) {
            $variant = $item->variant;
            $quantity = (float) $item->quantity;
            $unitPrice = $variant->getBasePrice($item->price_type);

            // Assign the fresh unit price to the item so that all discount calculations
            // use the new price instead of the old one from the database.
            $item->unit_price = $unitPrice;

            $isNegotiable = $variant->isPriceNegotiable($item->price_type);
            $minPrice = $variant->getMinimumAllowedPrice($item->price_type);

            $unitDiscountValue = $item->monetary_unit_discount_amount;

            if ($unitDiscountValue > 0 && ! $isNegotiable) {
                throw new \RuntimeException(__('sale_invoice.item_not_negotiable', ['item' => $variant->name()]));
            }

            // Assert Price Does not Go Below Minimum
            $unitPriceAfterUnitDiscount = $unitPrice - $unitDiscountValue;
            if ($unitDiscountValue > 0 && round($unitPriceAfterUnitDiscount, 2) < round($minPrice, 2)) {
                throw new \RuntimeException(__('sale_invoice.item_below_minimum', ['item' => $variant->name(), 'min' => $minPrice]));
            }

            $lineTotalDiscount = $item->lineTotalDiscount();
            $itemSubtotalBeforeDiscount = $unitPrice * $quantity;
            $itemSubtotalAfterItemDiscount = $itemSubtotalBeforeDiscount - $lineTotalDiscount;

            $minimumAllowedSubtotal = $minPrice * $quantity;

            // Tax Calculation
            $taxRate = 0.0; // TAX FEATURE POSTPONED
            $taxAmount = round($itemSubtotalAfterItemDiscount * $taxRate / 100, 2);
            $lineTotal = round($itemSubtotalAfterItemDiscount + $taxAmount, 2);

            // Update item in database
            $item->update([
                'unit_price' => $unitPrice,
                'line_total_discount' => round($lineTotalDiscount, 2),
                'subtotal' => round($itemSubtotalBeforeDiscount, 2),
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ]);

            $subtotalsBeforeDiscountSum += $itemSubtotalBeforeDiscount;
            $subtotalsAfterDiscountSum += $itemSubtotalAfterItemDiscount;
            $totalMinimumAllowed += $minimumAllowedSubtotal;
            $totalTaxAmount += $taxAmount;
            $totalItemsDiscount += $lineTotalDiscount;
        }

        return [
            'subtotals_before_discount_sum' => $subtotalsBeforeDiscountSum,
            'subtotals_after_discount_sum' => $subtotalsAfterDiscountSum,
            'total_minimum_allowed' => $totalMinimumAllowed,
            'total_tax_amount' => $totalTaxAmount,
            'total_items_discount' => $totalItemsDiscount,
        ];
    }

    /**
     * Calculate the total global discount value based on the initial subtotals sum.
     */
    private function calculateGlobalDiscount(SaleInvoice $invoice, float $subtotalsAfterDiscountSum): float
    {
        $globalInvoiceDiscount = 0.0;

        if ($invoice->discount_type && $invoice->discount_amount > 0) {
            $discountAmount = (float) $invoice->discount_amount;

            if ($invoice->discount_type === DiscountType::Fixed) {
                $globalInvoiceDiscount = min($discountAmount, $subtotalsAfterDiscountSum);
            } else {
                $discountAmount = min($discountAmount, 100);
                $globalInvoiceDiscount = $subtotalsAfterDiscountSum * ($discountAmount / 100);
            }
        }

        return $globalInvoiceDiscount;
    }

    /**
     * Update the main invoice record in the database with the aggregated totals.
     */
    private function updateInvoiceTotals(SaleInvoice $invoice, array $totals): void
    {
        $invoice->update($totals);
    }

    /**
     * Finalize a sale invoice — deduct stock and lock the record.
     *
     * @throws Throwable
     */
    public function finalize(SaleInvoice $invoice): void
    {
        // Run the finalization process inside a database transaction to ensure data integrity
        DB::transaction(function () use ($invoice) {
            /** @var SaleInvoice $invoice */
            // Re-fetch the invoice and lock the row for update to prevent concurrent modifications
            $invoice = SaleInvoice::query()->where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            // Guard: ensure the invoice actually has items or extra items before finalizing
            if ($invoice->items()->count() === 0 && $invoice->extraItems()->count() === 0) {
                throw new \RuntimeException(__('sale_invoice.no_items_or_extras'));
            }

            // Guard: re-check status inside the lock to prevent double-finalization
            // If it's already finalized, just return without doing anything
            if ($invoice->isFinalized()) {
                return;
            }

            // Eager load the required relationships for inventory deduction
            $invoice->load([
                'items.variant.product',
                'extraItems',
            ]);

            // Iterate through each item to deduct stock
            foreach ($invoice->items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->variant;

                // Guard: ensure the variant actually belongs to the same store as the invoice
                if (! $variant || ! $variant->product || (int) $variant->product->store_id !== (int) $invoice->store_id) {
                    throw new \RuntimeException(
                        // todo translate this msg
                        "Variant [{$item->product_variant_id}] does not belong to store [{$invoice->store_id}]."
                    );
                }

                // Record the stock-out movement via the immutable ledger
                // InventoryService validates sufficient stock and throws InsufficientStockException if not enough
                InventoryService::make()->recordMovement(
                    variant: $variant,
                    type: MovementType::Sale,
                    quantity: (float) $item->quantity, // The amount being sold (deducted)
                    notes: "SI #{$invoice->invoice_number}", // Reference note for the movement
                    reference: $invoice, // Polymorphic relation link to this invoice
                );
            }

            // Lock the invoice record by updating its status and timestamp
            $invoice->update([
                'status' => SaleInvoiceStatus::Finalized,
                'finalized_at' => now(), // Record the exact moment it was finalized
                'finalized_by' => Auth::id(), // Record the user who finalized it
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Sale Return Methods
    // -------------------------------------------------------------------------

    /**
     * Calculates the exact refund breakdown for a single item being returned.
     *
     * This function determines how much of the invoice's global discount was applied
     * to this specific item and calculates the actual
     * monetary amount to refund per unit (effective_unit_refund).
     *
     * @param  SaleInvoiceItem  $originalItem  The original invoice item being returned
     * @return array{unit_prorated_global_discount: float, effective_unit_refund: float}
     */
    public function calculateRefundBreakdown(SaleInvoiceItem $originalItem): array
    {
        // Retrieve the parent invoice of the item
        $originalInvoice = $originalItem->invoice;

        // Fallback: If no invoice is attached, return defaults using the raw unit price
        if (! $originalInvoice) {
            return [
                'unit_prorated_global_discount' => 0.0,
                'effective_unit_refund' => (float) $originalItem->unit_price,
            ];
        }

        // Ensure all items on the invoice are loaded so we can calculate total weights
        $originalInvoice->loadMissing('items');

        // Note: The 'subtotal' column on SaleInvoiceItem is the gross amount BEFORE item discounts.
        // To get the true value of the items after item-level discounts, we MUST subtract
        // 'line_total_discount' from 'subtotal'. This is mathematically correct.
        $subtotalsAfterItemDiscountSum = $originalInvoice->subtotalsAfterItemDiscountSum();

        // Fetch the global discount applied to the entire invoice
        $globalDiscount = (float) $originalInvoice->global_discount_amount;

        // Calculate the specific item's value after its own line discount is applied
        $itemSubtotalAfterItemDiscount = (float) $originalItem->subtotal - (float) $originalItem->line_total_discount;
        $originalQuantity = (float) $originalItem->quantity;

        // Calculate how much of the global discount belongs to this specific item line
        $proratedGlobalDiscount = 0.0;
        if ($subtotalsAfterItemDiscountSum > 0) {
            // Find the percentage (weight) of this item's value relative to the whole invoice
            $weight = $itemSubtotalAfterItemDiscount / $subtotalsAfterItemDiscountSum;

            // Multiply the total global discount by this item's weight to get its prorated share
            $proratedGlobalDiscount = $weight * $globalDiscount;
        }

        // The effective total refund for the entire line is its discounted subtotal minus its share of the global discount
        $effectiveLineRefund = $itemSubtotalAfterItemDiscount - $proratedGlobalDiscount;

        // Divide by the original quantity to get the final refund amounts for a single unit
        $unitProratedGlobalDiscount = $originalQuantity > 0 ? ($proratedGlobalDiscount / $originalQuantity) : 0;
        $effectiveUnitRefund = $originalQuantity > 0 ? ($effectiveLineRefund / $originalQuantity) : 0;

        // Return the final values rounded to 4 decimal places for precision
        return [
            'unit_prorated_global_discount' => round($unitProratedGlobalDiscount, 4),
            'effective_unit_refund' => round($effectiveUnitRefund, 4),
        ];
    }

    /**
     * Recalculate and persist all financial totals on a Draft SaleReturnInvoice.
     * Called from Filament afterCreate/afterSave hooks.
     *
     * @throws Throwable
     */
    public function recalculateReturnTotals(SaleReturnInvoice $return): void
    {
        DB::transaction(function () use ($return) {
            // Lock the return row to serialize concurrent saves on the same draft.
            // Items and extraItems don't need separate locks — the parent lock
            // prevents any concurrent recalculation from interleaving.
            $return = SaleReturnInvoice::lockForUpdate()->findOrFail($return->id);

            $return->load(['items', 'extraItems']);

            $itemsRefundTotal = 0.0;

            foreach ($return->items as $returnItem) {
                $itemRefundTotal = round((float) $returnItem->effective_unit_refund * (float) $returnItem->quantity, 2);

                $returnItem->update([
                    'item_refund_total' => $itemRefundTotal,
                ]);

                $itemsRefundTotal += $itemRefundTotal;
            }

            $extraItemsTotal = $return->calculateExtraItemsTotal();

            $totalRefundAmount = round($itemsRefundTotal + $extraItemsTotal, 2);

            $return->update([
                'items_refund_total' => round($itemsRefundTotal, 2),
                'extra_items_total' => round($extraItemsTotal, 2),
                'total_refund_amount' => $totalRefundAmount,
            ]);
        });
    }

    /**
     * Finalize a sale return.
     *
     * @throws Throwable
     */
    public function finalizeReturn(SaleReturnInvoice $return, ?int $userId = null): void
    {
        DB::transaction(function () use ($return, $userId) {
            /** @var SaleReturnInvoice $return */
            $return = SaleReturnInvoice::lockForUpdate()->findOrFail($return->id);

            if ($return->isFinalized()) {
                return; // Idempotent — no-op if already finalized
            }
            if ($return->items()->count() === 0 && $return->extraItems()->count() === 0) {
                throw new \RuntimeException(__('sale_return.no_items_or_extras'));
            }

            if ($return->original_invoice_id) {
                // Lock the original invoice to serialize any concurrent returns for this invoice
                SaleInvoice::lockForUpdate()->findOrFail($return->original_invoice_id);
            }

            $return->load('items.variant.product', 'originalInvoice.items', 'extraItems');

            foreach ($return->items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->variant;

                // Guard: variant must belong to the return's store
                if ((int) $variant->product->store_id !== (int) $return->store_id) {
                    throw new \RuntimeException(
                        "Variant [{$variant->id}] does not belong to store [{$return->store_id}]."
                    );
                }

                $this->validateReturnLineQuantity($item);

                // Record StockIn movement — goods physically return to the store
                InventoryService::make()->recordMovement(
                    variant: $variant,
                    type: MovementType::SaleReturn,
                    quantity: (float) $item->quantity,
                    notes: "SR #{$return->return_number}",
                    reference: $return,
                );
            }

            $return->update([
                'status' => SaleReturnStatus::Finalized,
                'finalized_at' => now(),
                'finalized_by' => $userId ?? Auth::id(),
            ]);

            if ($return->original_invoice_id) {
                $this->syncInvoiceReturnStatus($return->originalInvoice);
            }
        });

        // We will dispatch an event if needed in the future, e.g. SaleReturnFinalized
        // event(new SaleReturnFinalized($return));
    }

    private function syncInvoiceReturnStatus(SaleInvoice $invoice): void
    {
        $invoice->load('items');

        $isFullyReturned = true;
        $hasAnyReturn = false;

        foreach ($invoice->items as $originalItem) {
            $returnedQty = $originalItem->getFinalizedReturnedQuantity();
            $originalQty = (float) $originalItem->quantity;

            if ($returnedQty > 0) {
                $hasAnyReturn = true;
            }

            if ($returnedQty < $originalQty) {
                $isFullyReturned = false;
            }
        }

        $returnStatus = match (true) {
            // The only scenario where $isFullyReturned is true but $hasAnyReturn is false is
            // if the foreach loop never executes — i.e., the invoice has zero items.
            $isFullyReturned && $hasAnyReturn => SaleInvoiceReturnStatus::FullyReturned,
            $hasAnyReturn => SaleInvoiceReturnStatus::PartiallyReturned,
            default => SaleInvoiceReturnStatus::None,
        };

        $invoice->update(['return_status' => $returnStatus]);
    }

    /**
     * Validate that the return quantity on a line does not exceed the
     * remaining returnable quantity (original qty − already finalized return qty).
     *
     * Note: The caller must eager load the `variant` relationship on the items
     * before calling this inside a loop to prevent N+1 query issues.
     *
     * @throws \RuntimeException
     */
    private function validateReturnLineQuantity(SaleReturnInvoiceItem $item): void
    {
        $originalItem = $item->originalItem;

        if (! $originalItem) {
            throw new \RuntimeException(
                __('sale_return.missing_original_item', [
                    'item_name' => $item->variant?->full_qualified_name ?? __('app.unknown_product'),
                ])
            );
        }

        $remainingReturnable = $originalItem->getRemainingReturnableQuantity($item->id);

        if ((float) $item->quantity > $remainingReturnable) {

            throw new \RuntimeException(
                __('sale_return.exceeds_returnable_quantity_for_item', [
                    'max' => $remainingReturnable,
                    'item_name' => $item->variant?->full_qualified_name,
                ])
            );
        }
    }
}
