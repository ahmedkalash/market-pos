<?php

namespace App\Filament\Resources\SaleReturnInvoices\Pages;

use App\Filament\Resources\SaleReturnInvoices\SaleReturnInvoiceResource;
use App\Models\SaleReturnInvoice;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSaleReturnInvoice extends ViewRecord
{
    protected static string $resource = SaleReturnInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('app.finalize'))
                ->requiresConfirmation()
                ->modalHeading(__('sale_return.finalize_confirm_title'))
                ->modalDescription(__('sale_return.finalize_confirm_body'))
                ->successNotificationTitle(__('sale_return.finalize_success'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (SaleReturnInvoice $record): bool => ! $record->isFinalized())
                ->authorize('finalize_sale_return')
                ->action(function (SaleReturnInvoice $record): void {
                    try {
                        SaleInvoiceService::make()->finalizeReturn($record);
                        Notification::make()
                            ->title(__('app.success'))
                            ->body(__('sale_return.finalized_successfully'))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('app.error'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        $this->halt(true);
                    }

                    $this->refreshFormData([
                        'status',
                        'finalized_at',
                        'total_refund_amount',
                    ]);
                }),

            EditAction::make()
                ->authorize('update_sale_return')
                ->visible(fn (SaleReturnInvoice $record): bool => ! $record->isFinalized()),

            DeleteAction::make()
                ->authorize('delete_sale_return')
                ->visible(fn (SaleReturnInvoice $record): bool => ! $record->isFinalized()),
        ];
    }
}
