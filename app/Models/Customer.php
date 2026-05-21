<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Concerns\BelongsToCompany;

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
}
