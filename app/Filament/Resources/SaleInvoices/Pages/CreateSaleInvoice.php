<?php

namespace App\Filament\Resources\SaleInvoices\Pages;

use App\Enums\PaymentMethod;
use App\Enums\SequenceType;
use App\Filament\Resources\SaleInvoices\SaleInvoiceResource;
use App\Models\SaleInvoice;
use App\Services\SaleInvoiceService;
use App\Services\SequenceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSaleInvoice extends CreateRecord
{
    protected static string $resource = SaleInvoiceResource::class;

    public bool $shouldFinalize = false;

    public ?string $selectedPaymentMethod = null;

    protected function getFormActions(): array
    {
        return [
            Action::make('createAndFinalize')
                ->label(__('sale_invoice.create_and_finalize'))
                ->authorize('finalize_sale_invoice')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->schema([
                    Select::make('payment_method')
                        ->label(__('sale_invoice.payment_method'))
                        ->options([
                            PaymentMethod::Cash->value => __('sale_invoice.payment_method_cash'),
                            PaymentMethod::Card->value => __('sale_invoice.payment_method_card'),
                            PaymentMethod::Split->value => __('sale_invoice.payment_method_split'),
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->shouldFinalize = true;
                    $this->selectedPaymentMethod = $data['payment_method'];
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
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
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

        SaleInvoiceService::make()->recalculateTotals($invoice);

        if ($this->shouldFinalize) {
            $this->authorize('finalize_sale_invoice');
            try {
                $paymentMethod = PaymentMethod::from($this->selectedPaymentMethod);
                SaleInvoiceService::make()->finalize($invoice, $paymentMethod);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('sale_invoice.finalize_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }
}
