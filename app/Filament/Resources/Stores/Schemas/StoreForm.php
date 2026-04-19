<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return StoreSchema::configure($schema);
    }
}
