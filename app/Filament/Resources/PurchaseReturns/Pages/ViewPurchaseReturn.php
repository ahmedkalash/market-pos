<?php

namespace App\Filament\Resources\PurchaseReturns\Pages;

use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Models\PurchaseReturn;
use App\Services\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseReturn extends ViewRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('purchase_return.finalize'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('purchase_return.finalize_confirm_title'))
                ->modalDescription(__('purchase_return.finalize_confirm_body'))
                ->modalSubmitActionLabel(__('purchase_return.finalize'))
                ->authorize('finalize_purchase_return')
                ->visible(fn (PurchaseReturn $record): bool => ! $record->isFinalized())
                ->action(function (PurchaseReturn $record): void {
                    try {
                        PurchaseInvoiceService::make()->finalizeReturn($record);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('purchase_return.finalize_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('purchase_return.finalize_success'))
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
                ->url(fn (PurchaseReturn $record): string => PurchaseReturnResource::getUrl('edit', ['record' => $record]))
                ->authorize('update_purchase_return')
                ->visible(fn (PurchaseReturn $record): bool => ! $record->isFinalized()),

            DeleteAction::make()
                ->authorize('delete_purchase_return')
                ->visible(fn (PurchaseReturn $record): bool => ! $record->isFinalized()),
        ];
    }
}
