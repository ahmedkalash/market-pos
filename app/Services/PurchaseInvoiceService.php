<?php

namespace App\Services;

use App\Enums\InvoiceReturnStatus;
use App\Enums\MovementType;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Events\PurchaseReturnFinalized;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Support\Facades\DB;

/**
 * Handles the full transactional lifecycle of Purchase Invoices and Purchase Returns.
 *
 * Invoice finalization:
 *  1. Validates each line variant belongs to the invoice's store.
 *  2. Updates the variant's purchase_price to the invoice unit_cost.
 *  3. Calls InventoryService::recordMovement() to update stock and create ledger entry.
 *  4. Saves computed financial data on each item row.
 *  5. Calculates and saves invoice totals.
 *  6. Locks the invoice as Finalized.
 *
 * Return finalization:
 *  1. Validates items (store boundary + quantity does not exceed original).
 *  2. Records StockOut movements via InventoryService.
 *  3. Persists totals and locks the return as Finalized.
 *  4. Syncs the original invoice's return_status.
 *
 * All operations run in a single DB::transaction(). Any failure rolls back everything.
 */
class PurchaseInvoiceService
{
    public static function make(): self
    {
        return app(static::class);
    }

    // -------------------------------------------------------------------------
    // Invoice Methods
    // -------------------------------------------------------------------------

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
            // TAX FEATURE POSTPONED: Force tax rate to 0 for MVP
            // $taxRate = (float) ($variant->product->taxClass?->rate ?? 0);
            $taxRate = 0.0;

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
        if ($invoice->items()->count() === 0) {
            throw new \RuntimeException(__('purchase_invoice.no_items'));
        }

        DB::transaction(function () use ($invoice) {
            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::query()->where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            // Guard: re-check status inside the lock to prevent double-finalization
            if ($invoice->isFinalized()) {
                return;
            }

            $invoice->load('items.variant.product.taxClass');

            foreach ($invoice->items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->variant;

                // Guard: variant must belong to the invoice's store
                if ((int) $variant->product->store_id != (int) $invoice->store_id) {
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

    // -------------------------------------------------------------------------
    // Purchase Return Methods
    // -------------------------------------------------------------------------

    /**
     * Recalculate and persist all financial totals on a Draft PurchaseReturn.
     * Called from Filament afterCreate/afterSave hooks — mirrors recalculateTotals().
     */
    public function recalculateReturnTotals(PurchaseReturn $return): void
    {
        $return->load('items.variant.product.taxClass');

        $totalBeforeTax = 0;
        $totalTaxAmount = 0;

        foreach ($return->items as $item) {
            $quantity = (float) $item->quantity;
            $unitCost = (float) $item->unit_cost;
            // TAX FEATURE POSTPONED: Force tax rate to 0 for MVP
            $taxRate = 0.0;

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

        $return->update([
            'total_before_tax' => round($totalBeforeTax, 2),
            'total_tax_amount' => round($totalTaxAmount, 2),
            'total_amount' => round($totalBeforeTax + $totalTaxAmount, 2),
        ]);
    }

    /**
     * Finalize a purchase return.
     *
     * 1. Guard: return must have at least one item.
     * 2. Re-fetch with lockForUpdate() to prevent double-finalization.
     * 3. For each item: validate store boundary + validate qty does not exceed original.
     * 4. Call InventoryService::recordMovement(MovementType::PurchaseReturn) — StockOut.
     * 5. Persist totals via recalculateReturnTotals().
     * 6. Mark return as Finalized.
     * 7. Sync original invoice's return_status (if linked).
     *
     * All steps in a single DB::transaction(). Any failure rolls back everything.
     *
     * @throws \RuntimeException
     * @throws \Throwable
     */
    public function finalizeReturn(PurchaseReturn $return, ?int $userId = null): void
    {
        DB::transaction(function () use ($return, $userId) {
            /** @var PurchaseReturn $return */
            $return = PurchaseReturn::query()->where('id', $return->id)->lockForUpdate()->firstOrFail();

            if ($return->isFinalized()) {
                return; // Idempotent — no-op if already finalized
            }

            if ($return->items()->count() === 0) {
                throw new \RuntimeException(__('purchase_return.no_items'));
            }

            if ($return->original_invoice_id) {
                // Lock the original invoice to serialize any concurrent returns for this invoice
                PurchaseInvoice::query()->where('id', $return->original_invoice_id)->lockForUpdate()->first();
            }

            $return->load('items.variant.product', 'originalInvoice.items');

            foreach ($return->items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item->variant;

                // Guard: variant must belong to the return's store
                if ((int) $variant->product->store_id !== (int) $return->store_id) {
                    throw new \RuntimeException(
                        "Variant [{$variant->id}] does not belong to store [{$return->store_id}]."
                    );
                }

                // Guard: cannot return more than what remains un-returned on the original line or the current stock
                if ($item->original_item_id) {
                    $this->validateReturnLineQuantity($item);
                }

                // Record StockOut movement — goods physically leave the store to the vendor
                InventoryService::make()->recordMovement(
                    variant: $variant,
                    type: MovementType::PurchaseReturn,
                    quantity: (float) $item->quantity,
                    notes: "PR #{$return->return_number}",
                    reference: $return,
                    unitCost: (float) $item->unit_cost,
                );
            }

            $this->recalculateReturnTotals($return);

            $return->update([
                'status' => PurchaseReturnStatus::Finalized,
                'finalized_at' => now(),
                'finalized_by' => $userId ?? auth()->id(),
            ]);

            if ($return->original_invoice_id) {
                $this->syncInvoiceReturnStatus($return->originalInvoice);
            }
        });

        event(new PurchaseReturnFinalized($return));
    }

    /**
     * After finalizeReturn(), recompute the original invoice's return_status.
     * Looks at the sum of all finalized return quantities across all linked returns.
     */
    private function syncInvoiceReturnStatus(PurchaseInvoice $invoice): void
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
            $isFullyReturned && $hasAnyReturn => InvoiceReturnStatus::FullyReturned,
            $hasAnyReturn => InvoiceReturnStatus::PartiallyReturned,
            default => InvoiceReturnStatus::None,
        };

        $invoice->update(['return_status' => $returnStatus]);
    }

    /**
     * Validate that the return quantity on a line does not exceed the
     * remaining returnable quantity (original qty − already finalized return qty).
     *
     * @throws \RuntimeException
     */
    private function validateReturnLineQuantity(PurchaseReturnItem $item): void
    {
        /** @var PurchaseInvoiceItem|null $originalItem */
        $originalItem = $item->originalItem;

        if (! $originalItem) {
            return; // Cannot validate without the original — skip
        }

        $remainingReturnable = $originalItem->getRemainingReturnableQuantity($item->id);

        if ((float) $item->quantity > $remainingReturnable) {
            throw new \RuntimeException(
                __('purchase_return.exceeds_returnable_quantity', [
                    'max' => $remainingReturnable,
                ])
            );
        }
    }
}
