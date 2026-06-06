<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PriceType;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    public function subtotalAfterItemDiscount(): float
    {
        $netUnitPriceAfterDiscount = (float) $this->unit_price - (float) $this->monetary_unit_discount_amount;

        return round($netUnitPriceAfterDiscount * (float) $this->quantity, 2);
    }

    protected function subtotalBeforeItemDiscount(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                return (float) $this->unit_price * (float) $this->quantity;
            }
        );
    }

    /**
     * Calculate the capped line total discount for this item.
     *
     * We use the `min()` function here as a critical safety mechanism to protect
     * against negative subtotals. If a user accidentally applies a discount amount
     * that is higher than the actual price of the item, the `min()` function
     * ensures the total discount applied will never exceed the subtotal itself.
     * This prevents the item's final price from dropping below exactly 0.00.
     */
    public function lineTotalDiscount(): float
    {
        $lineTotalDiscount = $this->monetary_unit_discount_amount * (float) $this->quantity;

        return min($lineTotalDiscount, $this->subtotalBeforeItemDiscount);
    }

    /**
     * Get the absolute monetary discount amount per unit using a bottom-up approach.
     * This avoids precision loss and   side-effects from dividing the line total by quantity.
     */
    protected function monetaryUnitDiscountAmount(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                if (! $this->discount_type || $this->unit_discount_amount <= 0) {
                    return 0.0;
                }

                if ($this->discount_type === DiscountType::Fixed) {
                    return round((float) $this->unit_discount_amount, 2);
                }

                if ($this->discount_type === DiscountType::Percentage) {
                    return round((float) $this->unit_price * ((float) $this->unit_discount_amount / 100.0), 2);
                }

                return 0.0;
            }
        );
    }
}
