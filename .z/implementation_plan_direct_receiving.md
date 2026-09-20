# todo:
## Invoice Cancellation / Void (Finalized Invoices)
      Problem: What if a fully finalized invoice was a mistake? "Deleting" it would break the audit trail. The correct ERP approach is a Void — which marks the invoice as cancelled and reverses all inventory movements it created.

      Solution: A PurchaseInvoiceStatus::Cancelled enum case + a void() method in PurchaseInvoiceService. The void process:

      Locks the invoice record with lockForUpdate().
      For each item: calls InventoryService::recordMovement(MovementType::StockOut) to reverse the original stock-in.
      Sets status → Cancelled, cancelled_at, cancelled_by, cancellation_reason.
      Creates a CancellationReason text snapshot on the record.
      Critical: This does NOT delete the purchase_invoices row. The record stays in the database permanently as a historical document. The audit trail is complete.

      New DB column on purchase_invoices:

      cancelled_at     — timestamp, nullable
      cancelled_by     — FK → users, nullable
      cancellation_reason — text, nullable
      Files to create/modify:

      [NEW] migration: add_cancellation_fields_to_purchase_invoices
      [MODIFY] PurchaseInvoiceStatus enum — add Cancelled case
      [MODIFY] PurchaseInvoiceService — add void(PurchaseInvoice, string $reason): void
      [MODIFY] PurchaseInvoice model — add isCancelled(): bool, cancellation relations
      [MODIFY] PurchaseInvoiceResource — add "Void Invoice" action (modal with reason input)
      [MODIFY] PurchaseInvoicesTable — badge: Cancelled = red/gray
      [MODIFY] MorphMap in AppServiceProvider — no change needed (same model)
      Concurrency Guard: The void() method re-fetches the invoice with lockForUpdate() inside a transaction and re-checks isFinalized() before proceeding — same pattern as finalize().