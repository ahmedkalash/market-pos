<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

/**
 * RoleResource manages the Spatie Roles within the SaaS multi-tenant context.
 */
class RoleResource extends Resource
{
    // The model being managed is the Spatie Role model
    protected static ?string $model = Role::class;

    // Use a shield icon for security-related resources
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    // Position it early in the sidebar
    protected static ?int $navigationSort = 2;

    /**
     * Group the resource under 'User Management' in the sidebar.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('app.user_management');
    }

    /**
     * Use localized strings for navigation labels.
     */
    public static function getNavigationLabel(): string
    {
        return __('app.roles_and_permissions');
    }

    /**
     * Singular label used in 'Create [Role]' or '[Role] created' messages.
     */
    public static function getModelLabel(): string
    {
        return __('app.role');
    }

    /**
     * Plural label used in top-level headings and buttons.
     */
    public static function getPluralModelLabel(): string
    {
        return __('app.roles_and_permissions');
    }

    /**
     * Enforce Multi-Tenant Scope.
     * Business Rule: A company admin should only see roles that belong to their specific company.
     * Super Admins can bypass this scope to see all roles (if implemented).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        
        // Scope by company_id unless the user is a global Super Admin
        if ($user && ! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        return $user && $user->isCompanyLevel() && $user->hasPermissionTo('view_role');
    }

    /**
     * Authorization Rule: Only Company-Level users with 'view_any_role' permission can access the index.
     * This prevents storefront-level users (Cashiers, Managers) from seeing role management.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user && $user->isCompanyLevel() && $user->hasPermissionTo('view_any_role');
    }

    /**
     * Authorization Rule: Users must have the 'create_role' permission.
     */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user && $user->isCompanyLevel() && $user->hasPermissionTo('create_role');
    }

    /**
     * Authorization Rule: Users can only edit roles from their own company.
     */
    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user &&
               $user->isCompanyLevel() &&
               $user->company_id === $record->company_id &&
               $user->hasPermissionTo('update_role');
    }

    /**
     * Business Rule: Standard roles (Company Admin, Store Manager, etc.) are IMMUTABLE.
     * They cannot be deleted through the UI to prevent breaking application logic.
     */
    public static function canDelete(Model $record): bool
    {
        // Check against the list of protected roles in config
        if (in_array($record->name, array_keys(config('company_standard_roles.roles', [])))) {
            return false;
        }

        $user = auth()->user();

        return $user &&
               $user->isCompanyLevel() &&
               $user->company_id === $record->company_id &&
               $user->hasPermissionTo('delete_role');
    }

    /**
     * Delegate form configuration to the Schema class for cleaner resource organization.
     */
    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    /**
     * Delegate table configuration to the Table class for cleaner resource organization.
     */
    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    /**
     * Map the resource routes to their respective pages.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
