<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PriceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleInvoiceItem extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'sale_invoice_id',
        'product_variant_id',
        'price_type',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'line_total',
        'discount_type',
        'unit_discount_amount',
        'line_total_discount',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_type' => PriceType::class,
            'discount_type' => DiscountType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_discount_amount' => 'decimal:4',
            'line_total_discount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SaleInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class, 'sale_invoice_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return HasMany<SaleReturnInvoiceItem, $this>
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnInvoiceItem::class, 'original_item_id');
    }

    /**
     * Gets the total quantity that has been finalized for return.
     */
    public function getFinalizedReturnedQuantity(?int $excludeReturnItemId = null): float
    {
        $query = $this->returnItems()
            ->whereHas('saleReturnInvoice', fn ($q) => $q->finalized());

        if ($excludeReturnItemId) {
            $query->where('id', '!=', $excludeReturnItemId);
        }

        return (float) $query->sum('quantity');
    }

    /**
     * Gets the remaining quota based strictly on the invoice.
     */
    public function getInvoiceReturnableQuantity(?int $excludeReturnItemId = null): float
    {
        $returned = $this->getFinalizedReturnedQuantity($excludeReturnItemId);

        return max(0.0, (float) $this->quantity - $returned);
    }

    /**
     * Gets the remaining quantity that can still be returned against this invoice line.
     * Note: Unlike Purchase Returns, Sale Returns are not constrained by current physical stock.
     */
    public function getRemainingReturnableQuantity(?int $excludeReturnItemId = null): float
    {
        return $this->getInvoiceReturnableQuantity($excludeReturnItemId);
    }
}
