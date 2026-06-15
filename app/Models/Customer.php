<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Concerns\BelongsToCompany;
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'address',
        'tax_number',
        'is_active',
    ];

    /**
     * @return HasMany<SaleInvoice>
     */
    public function saleInvoices()
    {
        return $this->hasMany(SaleInvoice::class);
    }

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
