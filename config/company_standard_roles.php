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
                'delete_any_user',
                'assign_role_to_user',
                // main dashboard '/company'
                'company_dashboard',
                // store settings page
                'manage_store_settings',
                // products
                'view_any_product',
                'view_product',
                'create_product',
                'update_product',
                'delete_product',
                'delete_any_product',
            ],
        ],
        Roles::CASHIER->value => [
            'permissions' => [
                'company_dashboard',
                // products
                'view_any_product',
                'view_product',
                'create_product',
                'update_product',
                'delete_product',
                'delete_any_product',
            ],
        ],
        Roles::STOCK_CLERK->value => [
            'permissions' => [
                // products
                'view_any_product',
                'view_product',
                'create_product',
                'update_product',
                'delete_product',
                'delete_any_product',
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
