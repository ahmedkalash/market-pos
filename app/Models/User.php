<?php

namespace App\Models;

use App\Enums\Roles;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasMedia
{
    /** @use HasFactory<UserFactory> */
    use BelongsToCompany, BelongsToStore, HasFactory, HasRoles, InteractsWithMedia, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'store_id',
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'email_verified_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->useDisk('public')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('avatar', 'thumb')
            ?: $this->getFirstMediaUrl('avatar')
            ?: null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Super Admins are not scoped to a company
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Other users must belong to an active company
        if (! $this->company?->isActive()) {
            return false;
        }

        return true;
    }

    public function isCompanyLevel(): bool
    {
        return $this->company_id !== null && $this->store_id === null;
    }

    public function isStoreLevel(): bool
    {
        return $this->company_id !== null && $this->store_id !== null;
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isCompanyAdmin(): bool
    {
        return $this->hasRole(Roles::COMPANY_ADMIN->value);
    }

    public function isStoreManager(): bool
    {
        return $this->hasRole(Roles::STORE_MANAGER->value);
    }

    public function isCashier(): bool
    {
        return $this->hasRole(Roles::CASHIER->value);
    }

    public function isStockClerk(): bool
    {
        return $this->hasRole(Roles::STOCK_CLERK->value);
    }

    public function isAccountant(): bool
    {
        return $this->hasRole(Roles::ACCOUNTANT->value);
    }

    public function isSuperAdmin(): bool
    {
        // super_admin role has company_id = NULL (global role).
        // Spatie's hasRole() filters by the current team ID, so it would miss NULL.
        // We qualify the table name to avoid ambiguity in the JOIN with model_has_roles.
        return $this->roles()
            ->where('roles.name', Roles::SUPER_ADMIN->value)
            ->whereNull('roles.company_id')
            ->exists();
    }

    /**
     * Determine if the current user ($this) can be managed by the given administrator ($admin).
     *
     * RULES ENFORCED:
     *  A user cannot perform CRUD on themselves via this interface.
     *  Strict company isolation (tenant barrier).
     *  A user can not manage or perform crud on his manager
     *  Company Admins can manage anyone in their company (including other Company Admins).
     *  Store-level users cannot manage company-level users.
     *  Store-level users can only manage users within their exact same store.
     *  A store user cannot manage a Store Manager, UNLESS they themselves are also a Store Manager.
     */
    public function isManageableBy(User $admin): bool
    {
        // 1. Cannot manage self
        if ($this->id === $admin->id) {
            return false;
        }

        // 2. Super admins
        if ($admin->isSuperAdmin()) {
            return true;
        }
        if ($this->isSuperAdmin()) {
            return false;
        }

        // 3. Strict Company Isolation
        if ($this->company_id !== $admin->company_id) {
            return false;
        }

        // 4. Company Admins have supreme power in their company
        // (This allows them to manage ANYONE, including other Company Admins)
        if ($admin->isCompanyAdmin()) {
            return true;
        }

        // 5. Protect Company Admins from lower roles
        // If we reach here, $admin is NOT a Company Admin.
        if ($this->isCompanyAdmin()) {
            return false;
        }

        // 6. Store-Level Admin Restrictions
        if ($admin->isStoreLevel()) {

            // Cannot escalate out of store to manage company-level users
            if ($this->isCompanyLevel()) {
                return false;
            }

            // Must be mathematically restricted to their exact physical store
            if ($this->store_id !== $admin->store_id) {
                return false;
            }

            // Protect Store Managers from lower store roles
            // If the target is a Store Manager, the admin MUST also be a Store Manager
            if ($this->isStoreManager() && ! $admin->isStoreManager()) {
                return false;
            }

            return true;
        }

        // 7. Company-Level Custom Role (e.g. HR Manager)
        // If we reach here, $admin is Company-Level but NOT a Company Admin.
        // They are allowed to manage any store-level user natively, OR any peer company-level user
        // (Given they have the correct Spatie permissions)
        return true;
    }
}
