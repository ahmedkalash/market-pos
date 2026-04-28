<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use BelongsToCompany, BelongsToStore;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'name_en',
        'name_ar',
    ];

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
