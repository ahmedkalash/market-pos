<?php

namespace App\Filament\Resources\PurchaseReturns\Pages;

use App\Enums\SequenceType;
use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Models\PurchaseReturn;
use App\Services\PurchaseInvoiceService;
use App\Services\SequenceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

/** @property PurchaseReturn|null $record */
class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    public bool $shouldFinalize = false;

    protected function getFormActions(): array
    {
        return [
            Action::make('createAndFinalize')
                ->label(__('purchase_return.create_and_finalize'))
                ->authorize(['create_purchase_return', 'finalize_purchase_return'])
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->create();
                }),
            $this->getCreateFormAction()
                ->label(__('purchase_return.save_as_draft'))
                ->authorize('create_purchase_return'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Generate concurrency-safe return number
        $data['return_number'] = SequenceService::make()->next(
            companyId: auth()->user()->company_id,
            type: SequenceType::PurchaseReturn,
        );

        return $data;
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

    protected function afterCreate(): void
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
