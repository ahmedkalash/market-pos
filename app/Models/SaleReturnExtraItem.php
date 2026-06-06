<?php

namespace App\Models;

use App\Enums\ExtraItemActionType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnExtraItem extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'sale_return_invoice_id',
        'name',
        'action_type',
        'amount',
        'is_refundable',
        'notes',
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
        ];
    }

    /**
     * @return BelongsTo<SaleReturnInvoice, $this>
     */
    public function saleReturnInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleReturnInvoice::class);
    }

    /**
     * Get the amount with the correct mathematical sign based on the action type.
     */
    protected function signedAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->action_type === ExtraItemActionType::Addition
                ? (float) $this->amount
                : -(float) $this->amount
        );
    }
}
