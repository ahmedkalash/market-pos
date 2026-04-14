<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Roles;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        return $schema
            ->components([
                Section::make(__('app.user_details'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.full_name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('app.email_address'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label(__('app.password'))
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->confirmed()
                            ->minLength(8),

                        TextInput::make('password_confirmation')
                            ->label(__('app.confirm_new_password'))
                            ->password()
                            ->dehydrated(false),

                        TextInput::make('phone')
                            ->label(__('app.phone'))
                            ->tel(),

                        Toggle::make('is_active')
                            ->label(__('app.active'))
                            ->default(true)
                            ->disabled(fn (?User $record) => $record && $record->id === $authUser->id)
                            ->dehydrated(),

                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label(__('app.avatar'))
                            ->collection('avatar')
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('app.role_and_assignment'))
                    ->schema([
                        Select::make('role')
                            ->label(__('app.role'))
                            ->options(function () use ($authUser) {
                                // 1. Restrict role visibility to the current tenant (Company)
                                $query = Role::query()
                                    ->where('company_id', $authUser->company_id)
                                    ->where('guard_name', 'web');

                                // 2. Enforce Company Admin Protection
                                // Only top-level administrators (Company Admins & Super Admins)
                                // have the authority to assign the 'company_admin' role to another user.
                                if (! $authUser->isCompanyAdmin() && ! $authUser->isSuperAdmin()) {
                                    $query->where('name', '!=', Roles::COMPANY_ADMIN->value);
                                }

                                // 3. Enforce Store Manager Protection
                                // Only Company Admins, Super Admins, and Store Managers can assign the 'store_manager' role.
                                if (! $authUser->isCompanyAdmin() && ! $authUser->isSuperAdmin() && ! $authUser->isStoreManager()) {
                                    $query->where('name', '!=', Roles::STORE_MANAGER->value);
                                }

                                return $query->pluck('name', 'name')
                                    ->mapWithKeys(fn ($name) => [$name => __('app.roles.'.$name)]);
                            })
                            ->visible($authUser->hasPermissionTo('assign_role_to_user'))
                            ->required()
                            ->live()
                            ->in(function (Select $component) {
                                return array_keys($component->getOptions());
                            })
                            ->dehydrated(),

                        // ========================================================================
                        // STORE ASSIGNMENT & TRANSFER LOGIC
                        // ========================================================================
                        Select::make('store_id')
                            ->label(__('app.assigned_store'))

                            // 1. TENANT ISOLATION:
                            // Ensure the dropdown only lists stores that belong to the
                            // authenticated user's company. (Super Admins bypass this).
                            ->relationship('store', 'name_en', function ($query) use ($authUser) {
                                if (! $authUser->isSuperAdmin()) {
                                    $query->where('company_id', $authUser->company_id);
                                }
                            })

                            ->visible(function (Get $get) use ($authUser): bool {
                                // 2. THE STORE MANAGER SHIELD (Strict Transfer Prevention):
                                // We strictly prohibit Store-level users (like Store Managers) from changing
                                // or assigning a store. Moving a user from Store A to Store B is exclusively
                                // the responsibility of Company-level administrators. By hiding the field here,
                                // the backend safely ignores any injected store_id. (For new users created by
                                // a manager, the store is automatically assigned in mutateFormDataBeforeCreate).
                                if ($authUser->isStoreLevel()) {
                                    return false;
                                }

                                if ($get('role') == Roles::COMPANY_ADMIN->value) {
                                    return false;
                                }

                                return true;
                            })
                            ->placeholder(function (Get $get) {
                                $role = $get('role');

                                if ($role == Roles::STORE_MANAGER->value) {
                                    return __('filament-forms::components.select.placeholder');
                                }

                                return __('app.all_stores');
                            })
                            ->required(function (Get $get) {
                                $role = $get('role');

                                return $role && ($role == Roles::STORE_MANAGER->value);
                            }),
                    ])
                    ->columns(2),
            ]);
    }
}
