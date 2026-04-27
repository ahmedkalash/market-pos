<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use BelongsToCompany;

    protected $table = 'units_of_measure';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
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
}
