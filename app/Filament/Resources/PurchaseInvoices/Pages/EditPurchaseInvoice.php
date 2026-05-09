<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Prevent editing finalized invoices — redirect back to view.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var PurchaseInvoice $invoice */
        $invoice = $this->record;

        if ($invoice->isFinalized()) {
            $this->redirect($this->getResource()::getUrl('view', ['record' => $invoice]));
        }
    }

    protected function afterSave(): void
    {
        /** @var PurchaseInvoice $invoice */
        $invoice = $this->record;

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
    }
}
