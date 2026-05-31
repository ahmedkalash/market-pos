<?php

namespace App\Filament\Resources\SaleReturnInvoices\Pages;

use App\Filament\Resources\SaleReturnInvoices\SaleReturnInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSaleReturnInvoices extends ListRecords
{
    protected static string $resource = SaleReturnInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
