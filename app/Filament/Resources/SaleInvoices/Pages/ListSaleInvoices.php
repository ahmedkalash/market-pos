<?php

namespace App\Filament\Resources\SaleInvoices\Pages;

use App\Filament\Resources\SaleInvoices\SaleInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSaleInvoices extends ListRecords
{
    protected static string $resource = SaleInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
