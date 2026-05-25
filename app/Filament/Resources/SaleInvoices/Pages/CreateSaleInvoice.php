<?php

namespace App\Filament\Resources\SaleInvoices\Pages;

use App\Enums\SequenceType;
use App\Filament\Resources\SaleInvoices\SaleInvoiceResource;
use App\Models\SaleInvoice;
use App\Services\SaleInvoiceService;
use App\Services\SequenceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSaleInvoice extends CreateRecord
{
    protected static string $resource = SaleInvoiceResource::class;

    protected bool $shouldFinalize = false;

    protected function getFormActions(): array
    {
        return [
            Action::make('createAndFinalize')
                ->label(__('sale_invoice.create_and_finalize'))
                ->authorize('finalize_sale_invoice')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->create();
                }),
            $this->getCreateFormAction()
                ->label(__('sale_invoice.save_as_draft'))
                ->authorize('create_sale_invoice'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Generate concurrency-safe invoice number.
        $data['invoice_number'] = SequenceService::make()->next(
            companyId: auth()->user()->company_id,
            type: SequenceType::SaleInvoice,
        );

        return $data;
    }

    protected function afterCreate(): void
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

            $this->record = null;
            $this->halt(true);
        }

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

                $this->record = null;
                $this->halt(true);
            }
        }
    }
}
