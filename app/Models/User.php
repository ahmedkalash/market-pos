<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->company?->isActive()) {
            return false;
        }

        return true;
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
        return $this->hasRole(\App\Enums\Roles::COMPANY_ADMIN->value);
    }

    public function isStoreManager(): bool
    {
        return $this->hasRole('Store Manager');
    }

    public function isCashier(): bool
    {
        return $this->hasRole('Cashier');
    }

    public function isStockClerk(): bool
    {
        return $this->hasRole('Stock Clerk');
    }

    public function isAccountant(): bool
    {
        return $this->hasRole('Accountant');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(\App\Enums\Roles::SUPER_ADMIN->value);
    }
}
