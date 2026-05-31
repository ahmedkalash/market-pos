<?php

namespace App\Filament\Resources\SaleInvoices\Pages;

use App\Filament\Resources\SaleInvoices\SaleInvoiceResource;
use App\Filament\Resources\SaleReturnInvoices\SaleReturnInvoiceResource;
use App\Models\SaleInvoice;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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
                ->successNotificationTitle(__('sale_invoice.finalized_success'))
                ->authorize('finalize_sale_invoice')
                ->visible(fn (SaleInvoice $record): bool => $record->isDraft())
                ->action(function (SaleInvoice $record): void {
                    try {
                        SaleInvoiceService::make()->finalize($record);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('sale_invoice.finalize_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        $this->halt(true);
                    }

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

            Action::make('create_return')
                ->label(__('app.create_return'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->url(fn (SaleInvoice $record): string => SaleReturnInvoiceResource::getUrl('create', ['original_invoice_id' => $record->id]))
                ->visible(fn (SaleInvoice $record): bool => $record->isFinalized()),
        ];
    }
}
