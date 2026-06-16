<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use BelongsToCompany;
    use HasActiveScope;

    protected $fillable = [
        'company_id',
        'name_ar',
        'name_en',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
