<?php

namespace App\Providers;

use App\Http\Middleware\AppLocale;
use App\Http\Middleware\ApplyTenantScopes;
use App\Models\User;
use App\Observers\UserObserver;
use BezhanSalleh\LanguageSwitch\Enums\TriggerStyle;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        $this->implicitlyGrantSuperAdminAllPermissions();

        $this->registerDynamicPermissionsGate();

        Livewire::addPersistentMiddleware([
            ApplyTenantScopes::class,
            AppLocale::class,
        ]);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->userPreferredLocale(config('app.locale'))
                ->locales(['en', 'ar'])
                ->nativeLabel()
                ->trigger(TriggerStyle::IconLabel, icon: Heroicon::Language);
        });
    }

    private function implicitlyGrantSuperAdminAllPermissions(): void
    {
        // Implicitly grant "Super Admin" role all permissions
        Gate::before(function (User $user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return null;
        });
    }

    private function registerDynamicPermissionsGate(): void
    {
        // Dynamic Gate to bypass the need for individual Policy classes
        Gate::before(function (User $user, $ability, $models) {

            // Standard Laravel or specific gates to skip
            $standardLaravelAbilities = ['access-admin-panel', 'use-translation-manager'];

            if (in_array($ability, $standardLaravelAbilities)) {
                return null;
            }

            // Get the model class or instance
            $model = $models[0] ?? null;
            $modelName = is_string($model) ? class_basename($model) : ($model ? class_basename(get_class($model)) : null);

            // The default expected Spatie permission is the raw ability
            $permissionName = $ability;

            if ($modelName) {
                // Map 'viewAny' on 'Store' to 'view_any_store'
                $snakeAbility = Str::snake($ability);
                $snakeModel = Str::snake($modelName);
                $permissionName = "{$snakeAbility}_{$snakeModel}";
            }

            $registrar = app(PermissionRegistrar::class);
            $permissions = $registrar->getPermissions();
            $guardName = 'web';

            // Check if the primary targeted permission exists in the database
            $primaryExists = $permissions->where('name', $permissionName)->where('guard_name', $guardName)->isNotEmpty();
            // Check if the raw fallback ability exists in the database
            $fallbackExists = $permissions->where('name', $ability)->where('guard_name', $guardName)->isNotEmpty();

            if (! $primaryExists && ! $fallbackExists) {
                return null;
            }

            $hasPermission = ($primaryExists && $user->hasPermissionTo($permissionName, $guardName)) ||
                             ($fallbackExists && $user->hasPermissionTo($ability, $guardName));

            if ($hasPermission) {
                return true;
            }

            // If permission exists but user doesn't have it
            return false;
        });
    }
}
