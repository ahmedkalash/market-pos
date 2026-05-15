<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ----- Draft-only actions -----
            Action::make('finalize')
                ->label(__('purchase_invoice.finalize'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('purchase_invoice.finalize_confirm_title'))
                ->modalDescription(__('purchase_invoice.finalize_confirm_body'))
                ->modalSubmitActionLabel(__('purchase_invoice.finalize'))
                ->authorize('finalize_purchase_invoice')
                ->visible(fn (PurchaseInvoice $record): bool => $record->isDraft())
                ->action(function (PurchaseInvoice $record): void {
                    try {
                        PurchaseInvoiceService::make()->finalize($record);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('purchase_invoice.finalize_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('purchase_invoice.finalize'))
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'finalized_at',
                        'total_before_tax',
                        'total_tax_amount',
                        'total_amount',
                    ]);
                }),

            Action::make('edit')
                ->label(__('filament-actions::edit.single.label'))
                ->icon('heroicon-o-pencil-square')
                ->url(fn (PurchaseInvoice $record): string => PurchaseInvoiceResource::getUrl('edit', ['record' => $record]))
                ->authorize('update_purchase_invoice')
                ->visible(fn (PurchaseInvoice $record): bool => $record->isDraft()),

            DeleteAction::make()
                ->authorize('delete_purchase_invoice')
                ->visible(fn (PurchaseInvoice $record): bool => $record->isDraft()),

            // ----- Finalized-only actions (return management) -----
            Action::make('returnItems')
                ->label(__('purchase_invoice.return_items'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->authorize('create_purchase_return')
                ->visible(fn (PurchaseInvoice $record): bool => $record->isFinalized() && ! $record->isFullyReturned())
                ->url(fn (PurchaseInvoice $record): string => PurchaseReturnResource::getUrl('create', [
                    'original_invoice_id' => $record->id,
                ])),
        ];
    }
}
