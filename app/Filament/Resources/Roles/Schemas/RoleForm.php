<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Models\Role;

/**
 * RoleForm defines the schema for creating and editing roles.
 */
class RoleForm
{
    /**
     * Configure the main schema for the Role resource.
     * Business Rule: Role details are separated from permissions for better visual organization.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Primary section for basics like the unique role name.
                Section::make(__('app.role_details'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.name'))
                            ->required()
                            ->maxLength(255)
                            // Custom rendering logic: if it's a standard role, show the translated human-friendly name.
                            ->formatStateUsing(function (?string $state) {
                                if ($state && in_array($state, array_keys(config('company_standard_roles.roles', [])))) {
                                    return __('roles.'.$state);
                                }

                                return $state;
                            })
                            // Security Rule: Standard roles cannot have their name changed.
                            ->disabled(fn (?Role $record) => $record && in_array($record->name, array_keys(config('company_standard_roles.roles', []))))
                            // Multi-tenant check: hidden/un-dehydrated for standard roles to prevent data corruption.
                            ->dehydrated(fn (?Role $record) => ! ($record && in_array($record->name, array_keys(config('company_standard_roles.roles', [])))))
                            // Enforce uniqueness within the company scope.
                            ->unique(
                                table: config('permission.table_names.roles'),
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule) {
                                    /** @var User $user */
                                    $user = auth()->user();

                                    return $rule->where('company_id', $user->company_id)
                                        ->where('guard_name', 'web');
                                }
                            ),
                    ])
                    ->columns(1),

                // Comprehensive permissions section.
                Section::make(__('app.permissions'))
                    ->description(__('app.assign_permissions_to_role'))
                    ->schema([
                        // Display permissions in a 3-column responsive grid.
                        Grid::make(3)
                            ->schema(static::getPermissionCheckboxes()),
                    ]),
            ])
            // Force sections into a single vertical stack.
            ->columns(1);
    }

    /**
     * Build the permission checkbox lists based on the configuration file.
     * Business Logic: Permissions are grouped by module (Users, Stores, etc.) for easier management.
     */
    private static function getPermissionCheckboxes(): array
    {
        $permissionsConfig = config('company_permissions.permissions', []);
        $components = [];

        foreach ($permissionsConfig as $moduleName => $permissions) {
            $options = [];
            foreach ($permissions as $permission) {
                // Fetch the localized translation for each specific permission key.
                $translationKey = 'permissions.'.$permission;
                $label = __($translationKey);
                // Fallback to title case if translation is missing.
                if ($label === $translationKey) {
                    $label = str($permission)->replace('_', ' ')->title();
                }
                $options[$permission] = $label;
            }

            // Translate module headings, ensuring we handle potential array responses from __().
            $translatedModule = __('permissions.'.strtolower($moduleName));
            if (is_array($translatedModule) || $translatedModule === 'permissions.'.strtolower($moduleName)) {
                $translatedModule = $moduleName;
            }

            // Create an individual CheckboxList for each module to prevent multi-column overlap issues.
            $components[] = CheckboxList::make('permissions_'.str($moduleName)->snake())
                ->label($translatedModule)
                ->belowLabel('-------------')
                ->options($options)
                ->columns(1)
                ->bulkToggleable();
        }

        return $components;
    }
}
