<?php

namespace App\Filament\Resources\PurchaseReturns\Pages;

use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Models\PurchaseReturn;
use App\Services\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPurchaseReturn extends EditRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    public bool $shouldFinalize = false;

    protected function getFormActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('purchase_return.finalize'))
                ->modalHeading(__('purchase_return.finalize_confirm_title'))
                ->modalDescription(__('purchase_return.finalize_confirm_body'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->authorize(['update_purchase_return', 'finalize_purchase_return'])
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->save();
                }),
            $this->getSaveFormAction()
                ->label(__('purchase_return.save_as_draft'))
                ->authorize('update_purchase_return'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Prevent editing finalized returns — redirect back to view.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var PurchaseReturn $return */
        $return = $this->record;

        if ($return->isFinalized()) {
            $this->redirect($this->getResource()::getUrl('view', ['record' => $return]));
        }
    }

    /**
     * @throws Halt
     */
    protected function afterValidate(): void
    {
        // Filter out repeater rows with 0 quantity
        if (isset($this->data['items']) && is_array($this->data['items'])) {
            $this->data['items'] = array_filter(
                $this->data['items'],
                fn ($item) => (float) ($item['quantity'] ?? 0) > 0
            );

            if (empty($this->data['items'])) {
                Notification::make()
                    ->title(__('purchase_return.no_items_to_return'))
                    ->danger()
                    ->send();

                $this->halt();
            }
        }
    }

    protected function afterSave(): void
    {
        /** @var PurchaseReturn $return */
        $return = $this->record;

        PurchaseInvoiceService::make()->recalculateReturnTotals($return);

        if ($this->shouldFinalize) {
            $this->authorize('finalize_purchase_return');
            try {
                PurchaseInvoiceService::make()->finalizeReturn($return);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('purchase_return.finalize_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }
}
