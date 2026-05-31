<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnInvoiceItem extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'sale_return_invoice_id',
        'product_variant_id',
        'original_item_id',
        'quantity',
        'unit_price',
        'unit_discount_amount',
        'prorated_global_discount',
        'effective_unit_refund',
        'item_refund_total',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'unit_discount_amount' => 'decimal:4',
            'prorated_global_discount' => 'decimal:4',
            'effective_unit_refund' => 'decimal:4',
            'item_refund_total' => 'decimal:2',
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
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<SaleInvoiceItem, $this>
     */
    public function originalItem(): BelongsTo
    {
        return $this->belongsTo(SaleInvoiceItem::class, 'original_item_id');
    }
}
