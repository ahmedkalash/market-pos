<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Company extends Model implements HasMedia
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'name_en',
        'name_ar',
        'slug',
        'logo',
        'email',
        'phone',
        'address',
        'vat_number',
        'vat_rate',
        'currency',
        'locale',
        'receipt_header',
        'receipt_footer',
        'receipt_show_logo',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'receipt_show_logo' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Store, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isSubscriptionValid(): bool
    {
        // TODO
        return true;
    }

    public function isOnTrial(): bool
    {
        return $this->plan?->isTrial() ?? false;
    }
}
