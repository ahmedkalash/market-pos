<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'purchase_return_id',
        'product_variant_id',
        'original_item_id',
        'quantity',
        'unit_cost',
        'unit_discount_amount',
        'unit_prorated_global_discount',
        'effective_unit_refund',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'line_total',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'unit_discount_amount' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected function lineTotalDiscount(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) ($this->unit_discount_amount * $this->quantity),
        );
    }

    public function subtotalBeforeItemDiscount(): float
    {
        return $this->quantity * $this->unit_cost;
    }

    public function subtotalAfterItemDiscount(): float
    {
        return $this->subtotalBeforeItemDiscount() - $this->line_total_discount;
    }

    /**
     * @return BelongsTo<PurchaseReturn, $this>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<PurchaseInvoiceItem, $this>
     */
    public function originalItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceItem::class, 'original_item_id');
    }
}
