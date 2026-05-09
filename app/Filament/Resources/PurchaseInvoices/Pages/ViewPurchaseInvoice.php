<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\PurchaseInvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
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
            Action::make('finalize')
                ->label(__('purchase_invoice.finalize'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('purchase_invoice.finalize_confirm_title'))
                ->modalDescription(__('purchase_invoice.finalize_confirm_body'))
                ->modalSubmitActionLabel(__('purchase_invoice.finalize'))
                ->visible(fn (PurchaseInvoice $record): bool => $record->status === PurchaseInvoiceStatus::Draft
                    && auth()->user()?->can('finalize_purchase_invoice')
                )
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
                ->url(fn (PurchaseInvoice $record): string => PurchaseInvoiceResource::getUrl('edit', ['record' => $record])
                )
                ->visible(fn (PurchaseInvoice $record): bool => $record->status === PurchaseInvoiceStatus::Draft
                    && auth()->user()?->can('update_purchase_invoice')
                ),

            DeleteAction::make()
                ->visible(fn (PurchaseInvoice $record): bool => $record->status === PurchaseInvoiceStatus::Draft
                    && auth()->user()?->can('delete_purchase_invoice')
                ),
        ];
    }
}
