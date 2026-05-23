<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\SaleInvoiceStatus;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
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

        $totalBeforeTax = 0;
        $totalTaxAmount = 0;

        foreach ($invoice->items as $item) {
            $quantity = (float) $item->quantity;
            $unitPrice = (float) $item->unit_price;
            // TAX FEATURE POSTPONED: Force tax rate to 0 for MVP
            $taxRate = 0.0;

            $subtotal = round($quantity * $unitPrice, 2);
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
                'finalized_by' => auth()->id(),
            ]);
        });
    }
}
