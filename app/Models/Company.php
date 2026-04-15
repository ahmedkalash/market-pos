<?php

namespace App\Models;

use App\Enums\CurrencyPosition;
use App\Enums\RoundingRule;
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
        'working_hours',
        'vat_number',
        'vat_rate',
        'tax_label',
        'tax_is_inclusive',
        'currency',
        'currency_symbol',
        'currency_position',
        'thousand_separator',
        'decimal_separator',
        'decimal_precision',
        'locale',
        'timezone',
        'date_format',
        'time_format',
        'receipt_header',
        'receipt_footer',
        'receipt_show_logo',
        'receipt_show_vat_number',
        'receipt_show_address',
        'invoice_prefix',
        'invoice_next_number',
        'rounding_rule',
        'enable_zatca_qr',
        'whatsapp_number',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'tax_is_inclusive' => 'boolean',
            'currency_position' => CurrencyPosition::class,
            'decimal_precision' => 'integer',
            'rounding_rule' => RoundingRule::class,
            'receipt_show_logo' => 'boolean',
            'receipt_show_vat_number' => 'boolean',
            'receipt_show_address' => 'boolean',
            'invoice_next_number' => 'integer',
            'enable_zatca_qr' => 'boolean',
            'is_active' => 'boolean',
            'working_hours' => 'array',
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
