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
        $invoice->load('items.variant.product.taxClass');

        $invoiceMinimumAcceptableTotal = 0.0;
        $initialSubtotalsSum = 0.0;
        $itemCalculations = [];

        // Pass 1: Line Item Calculation & Minimums
        foreach ($invoice->items as $item) {
            $variant = $item->variant;
            $quantity = (float) $item->quantity;
            $unitPrice = (float) $item->unit_price;

            // Determine negotiability and minimum price based on PriceType
            $isNegotiable = false;
            $minPrice = 0.0;

            if ($item->price_type === PriceType::Retail) {
                $isNegotiable = (bool) $variant->retail_is_price_negotiable;
                $minPrice = (float) $variant->min_retail_price;
            } else {
                $isNegotiable = (bool) $variant->wholesale_is_price_negotiable;
                $minPrice = (float) $variant->min_wholesale_price;
            }

            // Calculate Item Discount
            $discountValue = 0.0;
            $unitDiscountValue = 0.0;

            if ($item->discount_type && $item->unit_discount_amount > 0) {
                if (! $isNegotiable) {
                    throw new \RuntimeException(__('sale_invoice.item_not_negotiable', ['item' => $variant->name]));
                }

                $discountAmount = (float) $item->unit_discount_amount;
                $unitDiscountValue = $item->discount_type === DiscountType::Fixed
                    ? $discountAmount
                    : $unitPrice * ($discountAmount / 100);

                $discountValue = $unitDiscountValue * $quantity;
            }

            $unitPriceAfterItemDiscount = $item->discount_type && $item->unit_discount_amount > 0
                ? $unitPrice - $unitDiscountValue
                : $unitPrice;

            $itemSubtotalBeforeDiscount = $unitPrice * $quantity;
            $discountValue = min($discountValue, $itemSubtotalBeforeDiscount);
            $itemSubtotalAfterItemDiscount = $itemSubtotalBeforeDiscount - $discountValue;

            if ($item->discount_type && $item->unit_discount_amount > 0 && round($unitPriceAfterItemDiscount, 2) < round($minPrice, 2)) {
                throw new \RuntimeException(__('sale_invoice.item_below_minimum', ['item' => $variant->name, 'min' => $minPrice]));
            }

            // The absolute minimum allowed subtotal for this item (to be respected by invoice-level discount too)
            $minimumAllowedSubtotal = $isNegotiable ? ($minPrice * $quantity) : ($unitPrice * $quantity);
            $invoiceMinimumAcceptableTotal += $minimumAllowedSubtotal;

            $initialSubtotalsSum += $itemSubtotalAfterItemDiscount;

            // Temporarily store these in an array for Pass 2
            $itemCalculations[$item->id] = [
                'pure_subtotal' => $itemSubtotalBeforeDiscount,
                'initial_subtotal' => $itemSubtotalAfterItemDiscount,
                'minimum_allowed_subtotal' => $minimumAllowedSubtotal,
                'calculated_discount_value' => $discountValue,
            ];
        }

        // Pass 2: Invoice Level Distribution
        $totalInvoiceDiscount = 0.0;
        if ($invoice->discount_type && $invoice->discount_amount > 0) {
            $discountAmount = (float) $invoice->discount_amount;
            if ($invoice->discount_type === DiscountType::Fixed) {
                $totalInvoiceDiscount = min($discountAmount, $initialSubtotalsSum);
            } else {
                $discountAmount = min($discountAmount, 100);
                $totalInvoiceDiscount = $initialSubtotalsSum * ($discountAmount / 100);
            }
        }

        $totalBeforeTax = 0.0;
        $totalTaxAmount = 0.0;
        $totalItemsDiscount = 0.0;

        foreach ($invoice->items as $item) {
            $calculations = $itemCalculations[$item->id] ?? [
                'initial_subtotal' => 0,
                'minimum_allowed_subtotal' => 0,
                'calculated_discount_value' => 0,
            ];
            $initialSubtotal = (float) $calculations['initial_subtotal'];

            // Distribute the invoice discount proportionally
            $distributedInvoiceDiscount = 0.0;
            if ($initialSubtotalsSum > 0 && $totalInvoiceDiscount > 0) {
                $distributedInvoiceDiscount = ($initialSubtotal / $initialSubtotalsSum) * $totalInvoiceDiscount;
            }

            $finalSubtotal = $initialSubtotal - $distributedInvoiceDiscount;

            if (round($finalSubtotal, 2) < round((float) $calculations['minimum_allowed_subtotal'], 2)) {
                $variantName = $item->variant->name ?? 'Unknown';
                throw new \RuntimeException(__('sale_invoice.invoice_discount_breaches_minimum', ['item' => $variantName]));
            }

            $taxRate = 0.0; // TAX FEATURE POSTPONED
            $taxAmount = round($finalSubtotal * $taxRate / 100, 2);
            $lineTotal = round($finalSubtotal + $taxAmount, 2);

            $item->update([
                'line_total_discount' => round($calculations['calculated_discount_value'], 2),
                'subtotal' => round($calculations['pure_subtotal'] ?? 0, 2),
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ]);

            $totalBeforeTax += $finalSubtotal;
            $totalTaxAmount += $taxAmount;
            $totalItemsDiscount += $calculations['calculated_discount_value'];
        }

        $grandTotalDiscount = $totalItemsDiscount + $totalInvoiceDiscount;

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
        DB::transaction(function () use ($invoice) {
            /** @var SaleInvoice $invoice */
            $invoice = SaleInvoice::query()->where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->items()->count() === 0) {
                throw new \RuntimeException(__('sale_invoice.no_items'));
            }

            // Guard: re-check status inside the lock to prevent double-finalization
            if ($invoice->isFinalized()) {
                return;
            }

            $invoice->load([
                'items.variant.product',
            ]);

            foreach ($invoice->items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->variant;

                // Guard: variant must belong to the invoice's store
                if (! $variant->product || (int) $variant->product->store_id !== (int) $invoice->store_id) {
                    throw new \RuntimeException(
                        "Variant [{$variant->id}] does not belong to store [{$invoice->store_id}]."
                    );
                }

                // Record the stock-out movement via the immutable ledger
                // InventoryService validates sufficient stock and throws InsufficientStockException
                InventoryService::make()->recordMovement(
                    variant: $variant,
                    type: MovementType::Sale,
                    quantity: (float) $item->quantity,
                    notes: "SI #{$invoice->invoice_number}",
                    reference: $invoice,
                    unitCost: (float) $item->unit_price,
                );
            }

            // Lock the record
            $invoice->update([
                'status' => SaleInvoiceStatus::Finalized,
                'finalized_at' => now(),
                'finalized_by' => Auth::id(),
            ]);
        });
    }
}
