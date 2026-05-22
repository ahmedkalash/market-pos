<?php

namespace App\Filament\Resources\SaleInvoices\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\SaleInvoices\SaleInvoiceResource;
use App\Models\SaleInvoice;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSaleInvoice extends EditRecord
{
    protected static string $resource = SaleInvoiceResource::class;

    public bool $shouldFinalize = false;

    public ?string $selectedPaymentMethod = null;

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
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
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
