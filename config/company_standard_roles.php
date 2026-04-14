<?php

use App\Enums\Roles;

return [
    'roles' => [
        Roles::COMPANY_ADMIN->value => [
            // Company Admin has all permissions automatically mapped using global check
            // However, we can either leave it empty and let action resolve it, or specify 'ALL'
            'permissions' => ['ALL_COMPANY_PERMISSIONS'],
        ],
        Roles::STORE_MANAGER->value => [
            'permissions' => [
                // users
                'view_any_user',
                'view_user',
                'create_user',
                'update_user',
                'delete_user',
                'assign_role_to_user',
                // stores
                'view_any_store',
                'view_store',
                // main dashboard '/company'
                'company_dashboard',
            ],
        ],
        Roles::CASHIER->value => [
            'permissions' => [
                'company_dashboard',
                // Add more as POS features are implemented
            ],
        ],
        Roles::STOCK_CLERK->value => [
            'permissions' => [
                'view_any_store',
                'view_store',
            ],
        ],
        Roles::ACCOUNTANT->value => [
            'permissions' => [
                'view_any_store',
                'view_store',
                'view_any_company',
                'company_dashboard',
            ],
        ],
    ],
];
