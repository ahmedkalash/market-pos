<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Permissions
    |--------------------------------------------------------------------------
    |
    | Define all permissions the application needs here. They are synced to
    | the database by the RoleAndPermissionSeeder — no manual DB entry needed.
    |
    |--------------------------------------------------------------------------
    | NAMING CONVENTION (Filament + Global Gate)
    |--------------------------------------------------------------------------
    |
    | The AppServiceProvider Global Gate translates standard Laravel ability
    | checks into Spatie permission strings using this pattern: {snake_ability}_{snake_model}
    |
    | For example, when Filament checks `viewAny` on a `Store` model, the
    | Global Gate looks for a permission named `view_any_store`.
    |
    | STANDARD TEMPLATE — replace {model} with the snake_case, singular
    | model name:
    |
    |   'view_any_{model}',          // List / index page
    |   'view_{model}',              // Show / detail page
    |   'create_{model}',            // Create page
    |   'update_{model}',            // Edit page
    |   'delete_{model}',            // Single delete
    |   'delete_any_{model}',        // Bulk delete
    |
    */

    'permissions' => [
        'Dashboard' => [
            'company_dashboard',
        ],
        'Users' => [
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
            'delete_any_user',
            'assign_role_to_user',
        ],
        'Roles' => [
            'view_any_role',
            'view_role',
            'create_role',
            'update_role',
            'delete_role',
            'delete_any_role',
        ],

        'Stores' => [
            'view_any_store',
            'view_any_store',
            'view_store',
            'create_store',
            'update_store',
            'delete_store',
            'delete_any_store',
        ],
        'Companies' => [
            'view_any_company',
            'view_company',
            'update_company',
        ],
        'Plans' => [
            'view_any_plan',
            'view_plan',
        ],
        'Settings' => [
            'view_any_setting', // company settings
            'update_setting', // company settings
            'manage_store_settings',
        ],
        'Product Category' => [
            'view_any_product_category',
            'view_product_category',
            'create_product_category',
            'update_product_category',
            'delete_product_category',
            'delete_any_product_category',
        ],
        'Products' => [
            'view_any_product',
            'view_product',
            'create_product',
            'update_product',
            'delete_product',
            'delete_any_product',
        ],
        'Attributes' => [
            'view_any_attribute',
            'view_attribute',
            'create_attribute',
            'update_attribute',
            'delete_attribute',
            'delete_any_attribute',
        ],
        'Unit of Measures' => [
            'view_any_unit_of_measure',
            'view_unit_of_measure',
            'create_unit_of_measure',
            'update_unit_of_measure',
            'delete_unit_of_measure',
            'delete_any_unit_of_measure',
        ],
        'Brands' => [
            'view_any_brand',
            'view_brand',
            'create_brand',
            'update_brand',
            'delete_brand',
            'delete_any_brand',
        ],
        'Inventory' => [
            'adjust_stock',
            'view_inventory_movement',
        ],
        'Vendors' => [
            'view_any_vendor',
            'view_vendor',
            'create_vendor',
            'update_vendor',
            'delete_vendor',
            'delete_any_vendor',
        ],
        'Purchase Invoices' => [
            'view_any_purchase_invoice',
            'view_purchase_invoice',
            'create_purchase_invoice',
            'update_purchase_invoice',   // edit draft invoices
            'delete_purchase_invoice',   // delete draft invoices only
            'delete_any_purchase_invoice',
            'finalize_purchase_invoice', // lock invoice & update stock (senior staff only)
        ],

    ],
];
