<?php

namespace App\Actions;

use App\Models\Company;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Role;

class CreateDefaultCompanyRolesAction
{
    public function execute(Company $company): void
    {
        $guardName = 'web';
        $companyId = $company->id;
        $standardRoles = config('company_standard_roles.roles', []);

        foreach ($standardRoles as $roleName => $roleConfig) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guardName,
                'company_id' => $companyId
            ]);

            $permissionsToSync = $roleConfig['permissions'] ?? [];

            if (in_array('ALL_COMPANY_PERMISSIONS', $permissionsToSync)) {
                $permissionsToSync = Arr::flatten(config('company_permissions.permissions', []));
            }

            $role->syncPermissions($permissionsToSync);
        }
    }
}
