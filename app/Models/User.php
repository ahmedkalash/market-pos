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
}
