<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public bool $shouldFinalize = false;

    protected function getFormActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('purchase_invoice.finalize'))
                ->modalHeading(__('purchase_invoice.finalize_confirm_title') ?? __('purchase_invoice.finalize'))
                ->modalDescription(__('purchase_invoice.finalize_confirmation'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->authorize('finalize_purchase_invoice')
                ->successNotificationTitle(__('purchase_invoice.finalized_success'))
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->save();
                }),
            $this->getSaveFormAction()
                ->label(__('purchase_invoice.save_as_draft'))
                ->authorize('update_purchase_invoice'),
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

    /**
     * @throws Halt
     */
    protected function afterSave(): void
    {
        /** @var PurchaseInvoice $invoice */
        $invoice = $this->record;

        try {
            PurchaseInvoiceService::make()->recalculateTotals($invoice);
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('purchase_invoice.recalculate_failed') ?? 'Recalculation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt(true);
        }

        if ($this->shouldFinalize) {
            $this->shouldFinalize = false;

            $this->authorize('finalize_purchase_invoice');
            try {
                PurchaseInvoiceService::make()->finalize($invoice);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('purchase_invoice.finalize_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                $this->halt(true);
            }
        }
    }
}
