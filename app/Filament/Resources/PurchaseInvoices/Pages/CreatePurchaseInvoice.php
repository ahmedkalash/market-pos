<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\SequenceType;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use App\Services\SequenceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public bool $shouldFinalize = false;

    protected function getFormActions(): array
    {
        return [
            Action::make('createAndFinalize')
                ->label(__('purchase_invoice.create_and_finalize'))
                ->requiresConfirmation()
                ->modalHeading(__('purchase_invoice.finalize_confirm_title') ?? __('purchase_invoice.finalize'))
                ->modalDescription(__('purchase_invoice.finalize_confirmation'))
                ->successNotificationTitle(__('purchase_invoice.finalized_success'))
                ->authorize('finalize_purchase_invoice')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->create();
                }),
            $this->getCreateFormAction()
                ->label(__('purchase_invoice.save_as_draft'))
                ->authorize('create_purchase_invoice'),
            $this->getCancelFormAction(),
        ];
    }

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

    /**
     * @throws Halt
     */
    protected function afterCreate(): void
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

            $this->record = null;
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

                $this->record = null;
                $this->halt(true);
            }
        }
    }
}
