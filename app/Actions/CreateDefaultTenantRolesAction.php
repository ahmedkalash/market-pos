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

        // Tenant Admin - All tenant level permissions
        Role::firstOrCreate([
            'name' => Roles::TENANT_ADMIN->value, 
            'guard_name' => $guardName,
            'company_id' => $companyId
        ])->syncPermissions(Permission::where('guard_name', $guardName)->where(function($query) {
            $query->where('name', 'like', '%_user')
                  ->orWhere('name', 'like', '%_store')
                  ->orWhere('name', 'like', '%_company')
                  ->orWhere('name', 'like', '%_setting')
                  ->orWhere('name', 'company_dashboard');
        })->get());

        // Store Manager
        Role::firstOrCreate([
            'name' => Roles::STORE_MANAGER->value, 
            'guard_name' => $guardName,
            'company_id' => $companyId
        ])->syncPermissions([
            'view_any_user',
            'view_user',
            'view_any_store',
            'view_store',
            'company_dashboard',
        ]);

        // Cashier
        Role::firstOrCreate([
            'name' => Roles::CASHIER->value, 
            'guard_name' => $guardName,
            'company_id' => $companyId
        ])->syncPermissions([
            'company_dashboard',
            // Add more as POS features are implemented
        ]);

        // Stock Clerk
        Role::firstOrCreate([
            'name' => Roles::STOCK_CLERK->value, 
            'guard_name' => $guardName,
            'company_id' => $companyId
        ])->syncPermissions([
            'view_any_store',
            'view_store',
        ]);

        // Accountant
        Role::firstOrCreate([
            'name' => Roles::ACCOUNTANT->value, 
            'guard_name' => $guardName,
            'company_id' => $companyId
        ])->syncPermissions([
            'view_any_store',
            'view_store',
            'view_any_company',
            'company_dashboard',
        ]);
    }
}
