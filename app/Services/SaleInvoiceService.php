<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\PriceType;
use App\Enums\SaleInvoiceStatus;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
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
     */
    public function recalculateTotals(SaleInvoice $invoice): void
    {
        // Eager load related models to prevent N+1 query problems during calculation
        $invoice->load('items.variant.product.taxClass');

        // Track the absolute minimum acceptable total to ensure discounts don't drop the price too low
        $invoiceMinimumAcceptableTotal = 0.0;
        // Track the sum of all item subtotals (after item-level discounts) before global invoice discounts
        $initialSubtotalsSum = 0.0;
        // Temporarily store calculations for each item to be used in the second pass
        $itemCalculations = [];

        // Pass 1: Line Item Calculation & Minimums
        foreach ($invoice->items as $item) {
            // Retrieve the variant for the current item
            $variant = $item->variant;
            // Cast the quantity to a float for math operations
            $quantity = (float) $item->quantity;
            // Cast the unit price to a float for math operations
            $unitPrice = (float) $item->unit_price;

            // Determine negotiability and minimum price based on PriceType
            $isNegotiable = false;
            $minPrice = 0.0;

            // Check if the item's price type is Retail
            if ($item->price_type === PriceType::Retail) {
                // Determine if the retail price can be negotiated (discounted)
                $isNegotiable = (bool) $variant->retail_is_price_negotiable;
                // Get the lowest allowed retail price for this variant
                $minPrice = (float) $variant->min_retail_price;
            } else { // Otherwise, the price type must be Wholesale
                // Determine if the wholesale price can be negotiated (discounted)
                $isNegotiable = (bool) $variant->wholesale_is_price_negotiable;
                // Get the lowest allowed wholesale price for this variant
                $minPrice = (float) $variant->min_wholesale_price;
            }

            // Calculate Item Discount
            $discountValue = 0.0;
            $unitDiscountValue = 0.0;

            // If a discount type is set AND the discount amount is greater than zero
            if ($item->discount_type && $item->unit_discount_amount > 0) {
                // If the item is not allowed to be discounted, throw an exception
                if (! $isNegotiable) {
                    throw new \RuntimeException(__('sale_invoice.item_not_negotiable', ['item' => $variant->name]));
                }

                // Cast the discount amount to a float
                $discountAmount = (float) $item->unit_discount_amount;
                // Calculate the discount value per unit (fixed amount or percentage of the unit price)
                $unitDiscountValue = $item->discount_type === DiscountType::Fixed
                    ? $discountAmount // If fixed, the discount is the exact amount
                    : $unitPrice * ($discountAmount / 100); // If percentage, calculate the percentage of the unit price

                // The total discount for this line item is the unit discount multiplied by the quantity
                $discountValue = $unitDiscountValue * $quantity;
            }

            // Calculate the unit price after applying the item-level discount
            $unitPriceAfterItemDiscount = $item->discount_type && $item->unit_discount_amount > 0
                ? $unitPrice - $unitDiscountValue
                : $unitPrice;

            // Calculate the pure subtotal before any discounts (unit price * quantity)
            $itemSubtotalBeforeDiscount = $unitPrice * $quantity;
            // Ensure the discount does not exceed the subtotal (cannot have a negative price)
            $discountValue = min($discountValue, $itemSubtotalBeforeDiscount);
            // Calculate the subtotal after subtracting the item-level discount
            $itemSubtotalAfterItemDiscount = $itemSubtotalBeforeDiscount - $discountValue;

            // Check if the discounted unit price falls below the allowed minimum price
            if ($item->discount_type && $item->unit_discount_amount > 0 && round($unitPriceAfterItemDiscount, 2) < round($minPrice, 2)) {
                // Throw an exception if the price breaches the minimum allowed limit
                throw new \RuntimeException(__('sale_invoice.item_below_minimum', ['item' => $variant->name, 'min' => $minPrice]));
            }

            // The absolute minimum allowed subtotal for this item (to be respected by invoice-level discount too)
            // If negotiable, the minimum is minPrice * quantity. If not, it's the full unitPrice * quantity.
            $minimumAllowedSubtotal = $isNegotiable ? ($minPrice * $quantity) : ($unitPrice * $quantity);
            // Add this item's minimum allowed subtotal to the invoice's overall minimum threshold
            $invoiceMinimumAcceptableTotal += $minimumAllowedSubtotal;

            // Add the item's discounted subtotal to the invoice's initial sum (before global discounts)
            $initialSubtotalsSum += $itemSubtotalAfterItemDiscount;

            // Temporarily store these in an array for Pass 2
            $itemCalculations[$item->id] = [
                'pure_subtotal' => $itemSubtotalBeforeDiscount, // The original total without discounts
                'initial_subtotal' => $itemSubtotalAfterItemDiscount, // The total after item-level discounts
                'minimum_allowed_subtotal' => $minimumAllowedSubtotal, // The lowest this line can go
                'calculated_discount_value' => $discountValue, // The total discount amount for this line
            ];
        }

        // Pass 2: Invoice Level Distribution
        $totalInvoiceDiscount = 0.0;
        // If a global invoice discount is set and greater than zero
        if ($invoice->discount_type && $invoice->discount_amount > 0) {
            // Cast the global discount amount to a float
            $discountAmount = (float) $invoice->discount_amount;
            // Calculate the total global discount value
            if ($invoice->discount_type === DiscountType::Fixed) {
                // If fixed, the global discount is the amount (capped at the sum of all subtotals)
                $totalInvoiceDiscount = min($discountAmount, $initialSubtotalsSum);
            } else {
                // If percentage, cap the percentage at 100%
                $discountAmount = min($discountAmount, 100);
                // Calculate the percentage from the total initial sum
                $totalInvoiceDiscount = $initialSubtotalsSum * ($discountAmount / 100);
            }
        }

        // Initialize variables to track the final totals across all items
        $totalBeforeTax = 0.0;
        $totalTaxAmount = 0.0;
        $totalItemsDiscount = 0.0;

        // Iterate through each item again to distribute the global discount and calculate taxes
        foreach ($invoice->items as $item) {
            // Retrieve the stored calculations for this item (or default to 0)
            $calculations = $itemCalculations[$item->id] ?? [
                'initial_subtotal' => 0,
                'minimum_allowed_subtotal' => 0,
                'calculated_discount_value' => 0,
            ];
            // Retrieve the initial subtotal for this item (after item discounts, before global discount)
            $initialSubtotal = (float) $calculations['initial_subtotal'];

            // Distribute the global invoice discount proportionally to this item
            $distributedInvoiceDiscount = 0.0;
            // If there is a total sum and a global discount to distribute
            if ($initialSubtotalsSum > 0 && $totalInvoiceDiscount > 0) {
                // Calculate this item's share of the global discount based on its weight in the total sum
                $distributedInvoiceDiscount = ($initialSubtotal / $initialSubtotalsSum) * $totalInvoiceDiscount;
            }

            // Calculate the final subtotal for this item after subtracting its share of the global discount
            $finalSubtotal = $initialSubtotal - $distributedInvoiceDiscount;

            // Check if the final subtotal (after all discounts) drops below the minimum allowed threshold
            if (round($finalSubtotal, 2) < round((float) $calculations['minimum_allowed_subtotal'], 2)) {
                // Retrieve the variant name for the error message
                $variantName = $item->variant->name ?? 'Unknown';
                // Throw an exception preventing the invoice from being saved
                throw new \RuntimeException(__('sale_invoice.invoice_discount_breaches_minimum', ['item' => $variantName]));
            }

            // Calculate the tax rate (currently hardcoded to 0.0 as tax feature is postponed)
            $taxRate = 0.0; // TAX FEATURE POSTPONED
            // Calculate the tax amount for this line based on the final subtotal
            $taxAmount = round($finalSubtotal * $taxRate / 100, 2);
            // Calculate the total for this line (subtotal + tax)
            $lineTotal = round($finalSubtotal + $taxAmount, 2);

            // Update the line item record in the database with the calculated financial values
            $item->update([
                'line_total_discount' => round($calculations['calculated_discount_value'], 2),
                'subtotal' => round($calculations['pure_subtotal'] ?? 0, 2),
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ]);

            // Add this item's final subtotal to the invoice's total before tax
            $totalBeforeTax += $finalSubtotal;
            // Add this item's tax amount to the invoice's total tax
            $totalTaxAmount += $taxAmount;
            // Add this item's discount to the invoice's total item discounts
            $totalItemsDiscount += $calculations['calculated_discount_value'];
        }

        // Calculate the grand total discount (sum of all item discounts + the global invoice discount)
        $grandTotalDiscount = $totalItemsDiscount + $totalInvoiceDiscount;

        // Update the main invoice record in the database with the aggregated totals
        $invoice->update([
            'global_discount_amount' => round($totalInvoiceDiscount, 2),
            'grand_total_discount' => round($grandTotalDiscount, 2),
            'total_before_tax' => round($totalBeforeTax, 2),
            'total_tax_amount' => round($totalTaxAmount, 2),
            'total_amount' => round(max(0, $totalBeforeTax + $totalTaxAmount), 2),
        ]);
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
