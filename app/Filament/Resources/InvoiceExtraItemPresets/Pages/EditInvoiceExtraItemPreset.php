<?php

namespace App\Filament\Resources\InvoiceExtraItemPresets\Pages;

use App\Filament\Resources\InvoiceExtraItemPresets\InvoiceExtraItemPresetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceExtraItemPreset extends EditRecord
{
    protected static string $resource = InvoiceExtraItemPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
