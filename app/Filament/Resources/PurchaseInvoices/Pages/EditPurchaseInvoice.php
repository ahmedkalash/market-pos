<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public bool $shouldFinalize = false;

    protected function getFormActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('purchase_invoice.finalize'))
                ->modalHeading(__('purchase_invoice.finalize'))
                ->modalDescription(__('purchase_invoice.finalize_confirmation'))
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->save();
                }),
            $this->getSaveFormAction()
                ->label(__('purchase_invoice.save_as_draft'))
                ->action(function () {
                    $this->shouldFinalize = false;
                    $this->save();
                }),
            $this->getCancelFormAction(),
        ];
    }

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

        if ($this->shouldFinalize) {
            try {
                PurchaseInvoiceService::make()->finalize($invoice);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('purchase_invoice.finalize_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }
}
