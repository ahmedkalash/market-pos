<?php

namespace App\Filament\Resources\ShippingDestinations\Pages;

use App\Filament\Resources\ShippingDestinations\ShippingDestinationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShippingDestinations extends ManageRecords
{
    protected static string $resource = ShippingDestinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
