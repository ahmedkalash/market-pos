<?php

namespace App\Filament\Resources\SaleReturnInvoices\Pages;

use App\Enums\SequenceType;
use App\Filament\Resources\SaleReturnInvoices\SaleReturnInvoiceResource;
use App\Models\SaleReturnInvoice;
use App\Models\User;
use App\Services\SaleInvoiceService;
use App\Services\SequenceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;

/** @property SaleReturnInvoice|null $record */
class CreateSaleReturnInvoice extends CreateRecord
{
    protected static string $resource = SaleReturnInvoiceResource::class;

    public bool $shouldFinalize = false;

    protected function getFormActions(): array
    {
        return [
            Action::make('createAndFinalize')
                ->label(__('sale_return.create_and_finalize'))
                ->authorize(['create_sale_return_invoice', 'finalize_sale_return_invoice']) // Assuming similar permissions pattern, Filament uses standard policy methods implicitly but here we can rely on standard policies or just authorize
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function () {
                    $this->shouldFinalize = true;
                    $this->create();
                }),
            $this->getCreateFormAction()
                ->label(__('sale_return.save_as_draft')),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User $user */
        $user = Auth::user();

        $data['created_by'] = $user->id;

        // Generate concurrency-safe return number
        $data['return_number'] = SequenceService::make()->next(
            companyId: $user->company_id,
            type: SequenceType::SaleReturn,
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
                    ->title(__('sale_return.no_items_to_return'))
                    ->danger()
                    ->send();

                $this->halt();
            }
        }
    }

    /**
     * @throws Halt
     */
    protected function afterCreate(): void
    {
        /** @var SaleReturnInvoice $return */
        $return = $this->record;

        try {
            SaleInvoiceService::make()->recalculateReturnTotals($return);
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('sale_return.recalculate_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->record = null;
            $this->halt(true);
        }

        if ($this->shouldFinalize) {
            $this->shouldFinalize = false;

            try {
                SaleInvoiceService::make()->finalizeReturn($return);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title(__('sale_return.finalize_failed'))
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                $this->record = null;
                $this->halt(true);
            }
        }
    }
}
