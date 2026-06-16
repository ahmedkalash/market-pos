<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use App\Models\Concerns\HasActiveScope;
use Database\Factories\ShippingDestinationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingDestination extends Model
{
    /** @use HasFactory<ShippingDestinationFactory> */
    use BelongsToCompany, BelongsToStore, HasActiveScope, HasFactory;

    protected $fillable = [
        'company_id',
        'store_id',
        'name',
        'cost',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cost' => 'decimal:2',
    ];
}
