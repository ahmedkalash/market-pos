<?php

namespace App\Filament\Resources\SaleReturnInvoices\Pages;

use App\Filament\Resources\SaleReturnInvoices\SaleReturnInvoiceResource;
use App\Models\SaleReturnInvoice;
use App\Services\SaleInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditSaleReturnInvoice extends EditRecord
{
    protected static string $resource = SaleReturnInvoiceResource::class;

    public bool $shouldFinalize = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_if($this->record->isFinalized(), 403, __('sale_return.cannot_edit_finalized'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('app.finalize'))
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading(__('sale_return.finalize_confirm_title'))
                ->modalDescription(__('sale_return.finalize_confirm_body'))
                ->successNotificationTitle(__('sale_return.finalize_success'))
                ->authorize('finalize_sale_return')
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->save();
                }),
            DeleteAction::make()
                ->authorize('delete_sale_return'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
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
        }

        $hasItems = ! empty($this->data['items']);
        $hasExtraItems = ! empty($this->data['extraItems']);

        // Allow extra-items-only returns (e.g. restocking fee with no physical product return)
        if (! $hasItems && ! $hasExtraItems) {
            Notification::make()
                ->title(__('sale_return.no_items_or_extras'))
                ->danger()
                ->send();

            $this->halt();
        }
    }

    /**
     * @throws Halt
     */
    protected function afterSave(): void
    {
        /** @var SaleReturnInvoice $record */
        $record = $this->record;

        try {
            SaleInvoiceService::make()->recalculateReturnTotals($record);
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('sale_return.recalculate_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt(true);
        }

        if ($this->shouldFinalize) {
            $this->shouldFinalize = false;

            $this->authorize('finalize_sale_return');
            try {
                SaleInvoiceService::make()->finalizeReturn($record);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('sale_return.finalize_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                $this->halt(true);
            }
        }
    }
}
