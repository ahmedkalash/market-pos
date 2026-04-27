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
        'Dashboard' => [
            'company_dashboard',
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

    ],
];
