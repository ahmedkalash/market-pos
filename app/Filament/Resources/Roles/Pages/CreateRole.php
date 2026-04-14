<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * CreateRole handles the instantiation of new company roles.
 */
class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Prepare data before the record is saved to the database.
     * Business Rule: Every role MUST be strictly tied to the company of the user creating it.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Enforce multi-tenancy: attach current user's company_id
        $data['company_id'] = auth()->user()->company_id;
        // Force the guard name to 'web' for compatibility with our authentication stack.
        $data['guard_name'] = 'web';

        return $data;
    }

    /**
     * Perform post-creation tasks.
     * Business Logic: Synchronize permissions after the role model is persisted.
     */
    protected function afterCreate(): void
    {
        $permissions = [];
        // Flatten the multi-column checkbox data into a single permission array.
        foreach (config('company_permissions.permissions', []) as $module => $perms) {
            $key = 'permissions_'.str($module)->snake();
            if (isset($this->data[$key]) && is_array($this->data[$key])) {
                $permissions = array_merge($permissions, $this->data[$key]);
            }
        }

        // Use Spatie's syncPermissions to attach the selected permissions to the new role.
        $this->record->syncPermissions($permissions);
    }
}
