<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Store extends Model implements HasMedia
{
    /** @use HasFactory<StoreFactory> */
    use BelongsToCompany, HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name_en',
        'name_ar',
        'address',
        'phone',
        'whatsapp_number',
        'email',
        'working_hours',
        'is_active',
        'receipt_header',
        'receipt_footer',
        'receipt_show_logo',
        'receipt_show_vat_number',
        'receipt_show_address',
        'timezone',
        'locale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'working_hours' => 'array',
            'is_active' => 'boolean',
            'receipt_show_logo' => 'boolean',
            'receipt_show_vat_number' => 'boolean',
            'receipt_show_address' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->nonQueued();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getSetting(string $key)
    {
        return $this->getAttribute($key);
    }

    public function getResolvedWhatsappNumber(): ?string
    {
        return $this->getSetting('whatsapp_number');
    }

    public function getResolvedReceiptHeader(): ?string
    {
        return $this->getSetting('receipt_header');
    }

    public function getResolvedReceiptFooter(): ?string
    {
        return $this->getSetting('receipt_footer');
    }

    public function shouldShowLogo(): bool
    {
        return (bool) $this->getSetting('receipt_show_logo');
    }

    public function shouldShowVatNumber(): bool
    {
        return (bool) $this->getSetting('receipt_show_vat_number');
    }

    public function shouldShowAddress(): bool
    {
        return (bool) $this->getSetting('receipt_show_address');
    }

    public function getResolvedTimezone(): string
    {
        return $this->getSetting('timezone') ?? config('app.timezone');
    }

    public function getResolvedLocale(): string
    {
        return $this->getSetting('locale') ?? config('app.locale');
    }
}
