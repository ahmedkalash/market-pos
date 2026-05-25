<?php

namespace App\Filament\Resources\SaleInvoices\Pages;

use App\Filament\Resources\SaleInvoices\SaleInvoiceResource;
use App\Models\SaleInvoice;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSaleInvoice extends EditRecord
{
    protected static string $resource = SaleInvoiceResource::class;

    public bool $shouldFinalize = false;

    protected function getFormActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('sale_invoice.finalize'))
                ->modalHeading(__('sale_invoice.finalize_confirm_title') ?? __('sale_invoice.finalize'))
                ->modalDescription(__('sale_invoice.finalize_confirmation'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->authorize('finalize_sale_invoice')
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->save();
                }),
            $this->getSaveFormAction()
                ->label(__('sale_invoice.save_as_draft'))
                ->authorize('update_sale_invoice'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    /**
     * Prevent editing finalized invoices — redirect back to view.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var SaleInvoice $invoice */
        $invoice = $this->record;

        if ($invoice->isFinalized()) {
            $this->redirect($this->getResource()::getUrl('view', ['record' => $invoice]));
        }
    }

    protected function afterSave(): void
    {
        /** @var SaleInvoice $invoice */
        $invoice = $this->record;

        try {
            SaleInvoiceService::make()->recalculateTotals($invoice);
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('sale_invoice.recalculate_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt(true);
        }

        // Hydrate server-computed totals back into the form
        $this->refreshFormData(['total_amount', 'total_before_tax', 'total_tax_amount']);

        if ($this->shouldFinalize) {
            $this->shouldFinalize = false;

            $this->authorize('finalize_sale_invoice');
            try {
                SaleInvoiceService::make()->finalize($invoice);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('sale_invoice.finalize_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                $this->halt(true);
            }
        }
    }
}
