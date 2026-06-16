<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToCompany, BelongsToStore, HasActiveScope, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'category_id',
        'tax_class_id',
        'brand_id',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * @return BelongsTo<TaxClass, $this>
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    #[Scope]
    public function whereNameLike($query, string $name)
    {
        return $query->where('name_en', 'like', "%{$name}%")
            ->orWhere('name_ar', 'like', "%{$name}%");
    }

    public function getNameAttribute(): string
    {
        return $this->{lang_suffix('name')};
    }
}
