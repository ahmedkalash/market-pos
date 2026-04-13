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
            'assign_role',
        ],
        'Stores' => [
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
            'view_any_setting',
            'update_setting',
        ],
        'Dashboard' => [
            'company_dashboard',
        ],
    ],
];
