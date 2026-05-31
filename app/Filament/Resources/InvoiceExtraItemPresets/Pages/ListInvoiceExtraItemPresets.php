<?php

namespace App\Filament\Resources\InvoiceExtraItemPresets\Pages;

use App\Filament\Resources\InvoiceExtraItemPresets\InvoiceExtraItemPresetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceExtraItemPresets extends ListRecords
{
    protected static string $resource = InvoiceExtraItemPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
