<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'tax_number',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
