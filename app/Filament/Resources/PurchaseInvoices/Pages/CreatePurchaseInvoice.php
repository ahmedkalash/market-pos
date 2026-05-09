<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\SequenceType;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use App\Services\SequenceService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Generate concurrency-safe invoice number.
        // The panel's databaseTransactions wraps the entire create flow,
        // so SequenceService's lockForUpdate is safe here.
        $data['invoice_number'] = SequenceService::make()->next(
            companyId: auth()->user()->company_id,
            type: SequenceType::PurchaseInvoice,
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var PurchaseInvoice $invoice */
        $invoice = $this->record;

        PurchaseInvoiceService::make()->recalculateTotals($invoice);
    }
}
