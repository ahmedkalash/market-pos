<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Handles the full transactional finalization of a Purchase Invoice.
 *
 * On finalization:
 *  1. Validates each line variant belongs to the invoice's store.
 *  2. Updates the variant's purchase_price to the invoice unit_cost.
 *  3. Calls InventoryService::recordMovement() to update stock and create ledger entry.
 *  4. Saves computed financial data on each item row.
 *  5. Calculates and saves invoice totals.
 *  6. Locks the invoice as Finalized.
 *
 * All steps run in a single DB::transaction(). Any failure rolls back everything.
 *
 * Future (Phase 4.2): createFromPurchaseOrder() will pre-fill a Draft invoice
 * from a PO and then call finalize() — zero changes to this method required.
 */
class PurchaseInvoiceService
{
    public static function make(): self
    {
        return app(static::class);
    }

    /**
     * Authoritatively recalculates all financial totals for a persisted Purchase Invoice.
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
     * This ensures the database always holds mathematically correct totals regardless
     * of what the frontend reactive calculations produced.
     */
    public function recalculateTotals(PurchaseInvoice $invoice): void
    {
        $invoice->load('items.variant.product.taxClass');

        $totalBeforeTax = 0;
        $totalTaxAmount = 0;

        foreach ($invoice->items as $item) {
            /** @var ProductVariant $variant */
            $variant = $item->variant;

            $quantity = (float) $item->quantity;
            $unitCost = (float) $item->unit_cost;
            $taxRate = (float) ($variant->product->taxClass?->rate ?? 0);

            $subtotal = round($quantity * $unitCost, 2);
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $lineTotal = round($subtotal + $taxAmount, 2);

            $item->update([
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ]);

            $totalBeforeTax += $subtotal;
            $totalTaxAmount += $taxAmount;
        }

        $invoice->update([
            'total_before_tax' => round($totalBeforeTax, 2),
            'total_tax_amount' => round($totalTaxAmount, 2),
            'total_amount' => round($totalBeforeTax + $totalTaxAmount, 2),
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function finalize(PurchaseInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->load('items.variant.product.taxClass');

            foreach ($invoice->items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->variant;

                // Guard: variant must belong to the invoice's store
                if ($variant->product->store_id !== $invoice->store_id) {
                    throw new \RuntimeException(
                        "Variant [{$variant->id}] does not belong to store [{$invoice->store_id}]."
                    );
                }

                // Update the variant's purchase_price BEFORE calling InventoryService
                // so the ledger records the correct cost paid on this invoice.
                $variant->update(['purchase_price' => $item->unit_cost]);

                // Record the stock-in movement via the immutable ledger
                InventoryService::make()->recordMovement(
                    variant: $variant,
                    type: MovementType::StockIn,
                    quantity: (float) $item->quantity,
                    notes: "PI #{$invoice->invoice_number}",
                    reference: $invoice,
                    unitCost: (float) $item->unit_cost,
                );
            }

            // Lock the record
            $invoice->update([
                'status' => PurchaseInvoiceStatus::Finalized,
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
            ]);
        });
    }
}
