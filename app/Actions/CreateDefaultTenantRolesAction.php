<?php

namespace App\Actions;

use App\Enums\Roles;
use App\Models\Company;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateDefaultTenantRolesAction
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

            if (in_array('ALL_TENANT_PERMISSIONS', $permissionsToSync)) {
                $permissionsToSync = Permission::where('guard_name', $guardName)
                    ->where(function($query) {
                        $query->where('name', 'like', '%_user')
                            ->orWhere('name', 'like', '%_store')
                            ->orWhere('name', 'like', '%_company')
                            ->orWhere('name', 'like', '%_setting')
                            ->orWhere('name', 'company_dashboard');
                    })->get();
            }

            $role->syncPermissions($permissionsToSync);
        }
    }
}
