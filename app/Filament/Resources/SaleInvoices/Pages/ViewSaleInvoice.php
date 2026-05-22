<?php

namespace App\Filament\Resources\SaleInvoices\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\SaleInvoices\SaleInvoiceResource;
use App\Models\SaleInvoice;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSaleInvoice extends ViewRecord
{
    protected static string $resource = SaleInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ----- Draft-only actions -----
            Action::make('finalize')
                ->label(__('sale_invoice.finalize'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('sale_invoice.finalize_confirm_title'))
                ->modalDescription(__('sale_invoice.finalize_confirm_body'))
                ->modalSubmitActionLabel(__('sale_invoice.finalize'))
                ->authorize('finalize_sale_invoice')
                ->visible(fn (SaleInvoice $record): bool => $record->isDraft())
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
                ->action(function (SaleInvoice $record, array $data): void {
                    try {
                        $paymentMethod = PaymentMethod::from($data['payment_method']);
                        SaleInvoiceService::make()->finalize($record, $paymentMethod);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('sale_invoice.finalize_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('sale_invoice.finalize'))
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'payment_method',
                        'finalized_at',
                        'total_before_tax',
                        'total_tax_amount',
                        'total_amount',
                    ]);
                }),

            Action::make('edit')
                ->label(__('filament-actions::edit.single.label'))
                ->icon('heroicon-o-pencil-square')
                ->url(fn (SaleInvoice $record): string => SaleInvoiceResource::getUrl('edit', ['record' => $record]))
                ->authorize('update_sale_invoice')
                ->visible(fn (SaleInvoice $record): bool => $record->isDraft()),

            DeleteAction::make()
                ->authorize('delete_sale_invoice')
                ->visible(fn (SaleInvoice $record): bool => $record->isDraft()),
        ];
    }
}
