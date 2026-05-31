<?php

namespace App\Models;

use App\Enums\ExtraItemActionType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceExtraItemPreset extends Model
{
    use BelongsToCompany, BelongsToStore, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'name',
        'action_type',
        'amount',
        'is_refundable',
        'invoice_type',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_type' => ExtraItemActionType::class,
            'amount' => 'decimal:2',
            'is_refundable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
