<?php

namespace App\Models;

use App\Enums\ExtraItemActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceExtraItem extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'purchase_invoice_id',
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
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }
}
