<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * ListRoles displays the index table for roles.
 */
class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    /**
     * Define the global actions for the listing page.
     */
    protected function getHeaderActions(): array
    {
        return [
            // Standard action to navigate to the creation form.
            Actions\CreateAction::make(),
        ];
    }
}
