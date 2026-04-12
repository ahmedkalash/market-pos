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
        'email',
        'working_hours',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'working_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
