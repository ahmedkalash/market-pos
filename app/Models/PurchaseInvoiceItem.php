<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoiceItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'purchase_invoice_id',
        'product_variant_id',
        'quantity',
        'unit_cost',
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
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return HasMany<PurchaseReturnItem, $this>
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'original_item_id');
    }

    /**
     * Gets the total quantity that has been finalized for return.
     */
    public function getFinalizedReturnedQuantity(?int $excludeReturnItemId = null): float
    {
        $query = $this->returnItems()
            ->whereHas('purchaseReturn', fn ($q) => $q->finalized());

        if ($excludeReturnItemId) {
            $query->where('id', '!=', $excludeReturnItemId);
        }

        return (float) $query->sum('quantity');
    }

    /**
     * Gets the remaining quota based strictly on the invoice (ignores physical stock).
     */
    public function getInvoiceReturnableQuantity(?int $excludeReturnItemId = null): float
    {
        $returned = $this->getFinalizedReturnedQuantity($excludeReturnItemId);

        return max(0.0, (float) $this->quantity - $returned);
    }

    /**
     * Remaining quantity that can still be returned against this invoice line.
     * Constrained by both the invoice quota and current physical stock.
     */
    public function getRemainingReturnableQuantity(?int $excludeReturnItemId = null): float
    {
        // 1. What is remaining on the invoice
        $invoiceRemaining = $this->getInvoiceReturnableQuantity($excludeReturnItemId);

        // 2. What is physically available in stock
        $currentStock = (float) ($this->variant?->quantity ?? 0);

        // The true returnable amount is the lesser of the two
        return min($invoiceRemaining, max(0.0, $currentStock));
    }
}
