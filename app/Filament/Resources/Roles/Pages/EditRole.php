<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * EditRole handles the modification of existing roles and their associated permissions.
 */
class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Header actions for the edit page.
     * Business Rule: Standard/Protected roles cannot be deleted.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                // Hide the delete button if the role name matches a protected system role.
                ->hidden(fn ($record) => in_array($record->name, array_keys(config('company_standard_roles.roles', [])))),
        ];
    }

    /**
     * Map database data to form fields before the page is rendered.
     * Business Logic: Extract currently assigned permissions and populate the virtual checkbox lists.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $rolePermissions = $this->record->permissions->pluck('name')->toArray();
        foreach (config('company_permissions.permissions', []) as $module => $perms) {
            $key = 'permissions_'.str($module)->snake();
            // Fill each virtual field with the intersection of assigned permissions and the module's available permissions.
            $data[$key] = array_intersect($perms, $rolePermissions);
        }

        return $data;
    }

    /**
     * Update permissions after the role model itself is saved.
     * Business Logic: Gather all virtual permission fields and synchronize them.
     */
    protected function afterSave(): void
    {
        $permissions = [];
        foreach (config('company_permissions.permissions', []) as $module => $perms) {
            $key = 'permissions_'.str($module)->snake();
            if (isset($this->data[$key]) && is_array($this->data[$key])) {
                $permissions = array_merge($permissions, $this->data[$key]);
            }
        }

        // Use Spatie's syncPermissions to overwrite existing permissions with the new selection.
        $this->record->syncPermissions($permissions);
    }
}
