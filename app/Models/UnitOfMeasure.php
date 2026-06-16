<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use BelongsToCompany, BelongsToStore, HasFactory;

    protected $table = 'units_of_measure';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'name_en',
        'name_ar',
        'abbreviation_en',
        'abbreviation_ar',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'uom_id');
    }

    public function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->{lang_suffix('name')},
        );
    }
}
