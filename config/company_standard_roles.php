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
                // product categories
                'view_any_product_category',
                'view_product_category',
                'create_product_category',
                'update_product_category',
                'delete_product_category',
                'delete_any_product_category',
                // attributes
                'view_any_attribute',
                'view_attribute',
                'create_attribute',
                'update_attribute',
                'delete_attribute',
                'delete_any_attribute',
                // unit of measures
                'view_any_unit_of_measure',
                'view_unit_of_measure',
                'create_unit_of_measure',
                'update_unit_of_measure',
                'delete_unit_of_measure',
                'delete_any_unit_of_measure',
                // brands
                'view_any_brand',
                'view_brand',
                'create_brand',
                'update_brand',
                'delete_brand',
                'delete_any_brand',
            ],
        ],
        Roles::CASHIER->value => [
            'permissions' => [
                'company_dashboard',
                // products
                'view_any_product',
                'view_product',
                // product categories
                'view_any_product_category',
                'view_product_category',
                // attributes
                'view_any_attribute',
                'view_attribute',
                // unit of measures
                'view_any_unit_of_measure',
                'view_unit_of_measure',
                // brands
                'view_any_brand',
                'view_brand',
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
                // product categories
                'view_any_product_category',
                'view_product_category',
                'create_product_category',
                'update_product_category',
                // attributes
                'view_any_attribute',
                'view_attribute',
                'create_attribute',
                'update_attribute',
                // unit of measures
                'view_any_unit_of_measure',
                'view_unit_of_measure',
                'create_unit_of_measure',
                'update_unit_of_measure',
                // brands
                'view_any_brand',
                'view_brand',
                'create_brand',
                'update_brand',
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
