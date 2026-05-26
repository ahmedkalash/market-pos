<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\SaleInvoiceStatus;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
     * @throws \Throwable
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
            $totalAmount = max(0, $totalBeforeTax + $totalTaxAmount + $shippingCost);

            // Persist the final invoice totals
            $this->updateInvoiceTotals($invoice, [
                'subtotal' => round($subtotalsBeforeDiscountSum, 2),
                'global_discount_amount' => round($globalInvoiceDiscount, 2),
                'grand_total_discount' => round($grandTotalDiscount, 2),
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
        $lockedInvoice = SaleInvoice::query()->where('id', $invoice->id)->lockForUpdate()->firstOrFail();
        $lockedInvoice->load(['items' => fn ($query) => $query->lockForUpdate()]);
        $lockedInvoice->load('items.variant.product.taxClass');

        return $lockedInvoice;
    }

    /**
     * Calculate the absolute monetary unit discount value for a given line item.
     */
    private function calculateUnitDiscountValue(SaleInvoiceItem $item): float
    {
        if (! $item->discount_type || $item->unit_discount_amount <= 0) {
            return 0.0;
        }

        $unitDiscountAmount = (float) $item->unit_discount_amount;
        $unitPrice = (float) $item->unit_price;

        return $item->discount_type === DiscountType::Fixed
            ? $unitDiscountAmount
            : $unitPrice * ($unitDiscountAmount / 100);
    }

    /**
     * Calculate the capped line total discount for a given line item.
     */
    private function calculateLineTotalDiscount(SaleInvoiceItem $item): float
    {
        $unitDiscountValue = $this->calculateUnitDiscountValue($item);
        $quantity = (float) $item->quantity;
        $unitPrice = (float) $item->unit_price;
        $itemSubtotalBeforeDiscount = $unitPrice * $quantity;

        $lineTotalDiscount = $unitDiscountValue * $quantity;

        return min($lineTotalDiscount, $itemSubtotalBeforeDiscount);
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

            $unitDiscountValue = $this->calculateUnitDiscountValue($item);

            if ($unitDiscountValue > 0 && ! $isNegotiable) {
                throw new \RuntimeException(__('sale_invoice.item_not_negotiable', ['item' => $variant->name()]));
            }

            // Assert Price Does not Go Below Minimum
            $unitPriceAfterUnitDiscount = $unitPrice - $unitDiscountValue;
            if ($unitDiscountValue > 0 && round($unitPriceAfterUnitDiscount, 2) < round($minPrice, 2)) {
                throw new \RuntimeException(__('sale_invoice.item_below_minimum', ['item' => $variant->name(), 'min' => $minPrice]));
            }

            $lineTotalDiscount = $this->calculateLineTotalDiscount($item);
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
     * @throws \Throwable
     */
    public function finalize(SaleInvoice $invoice): void
    {
        // Run the finalization process inside a database transaction to ensure data integrity
        DB::transaction(function () use ($invoice) {
            /** @var SaleInvoice $invoice */
            // Re-fetch the invoice and lock the row for update to prevent concurrent modifications
            $invoice = SaleInvoice::query()->where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            // Guard: ensure the invoice actually has items before finalizing
            if ($invoice->items()->count() === 0) {
                throw new \RuntimeException(__('sale_invoice.no_items'));
            }

            // Guard: re-check status inside the lock to prevent double-finalization
            // If it's already finalized, just return without doing anything
            if ($invoice->isFinalized()) {
                return;
            }

            // Eager load the required relationships for inventory deduction
            $invoice->load([
                'items.variant.product',
            ]);

            // Iterate through each item to deduct stock
            foreach ($invoice->items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->variant;

                // Guard: ensure the variant actually belongs to the same store as the invoice
                if (! $variant->product || (int) $variant->product->store_id !== (int) $invoice->store_id) {
                    throw new \RuntimeException(
                        "Variant [{$variant->id}] does not belong to store [{$invoice->store_id}]."
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
                    unitCost: (float) $item->unit_price, // The price the item was sold at
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
}
