<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'uom_id',
        'name_en',
        'name_ar',
        'retail_price',
        'retail_is_price_negotiable',
        'min_retail_price',
        'purchase_price',
        'wholesale_enabled',
        'wholesale_price',
        'wholesale_is_price_negotiable',
        'min_wholesale_price',
        'wholesale_qty_threshold',
        'quantity',
        'low_stock_threshold',
        'low_stock_alert_fired',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retail_price' => 'decimal:2',
            'min_retail_price' => 'decimal:2',
            'retail_is_price_negotiable' => 'boolean',
            'purchase_price' => 'decimal:2',
            'wholesale_enabled' => 'boolean',
            'wholesale_price' => 'decimal:2',
            'wholesale_is_price_negotiable' => 'boolean',
            'min_wholesale_price' => 'decimal:2',
            'wholesale_qty_threshold' => 'decimal:3',
            'quantity' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'low_stock_alert_fired' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * @return HasMany<ProductBarcode, $this>
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /**
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'variant_attribute_value');
    }

    public function isLowStock(): bool
    {
        if ($this->low_stock_threshold === null) {
            return false;
        }

        return $this->quantity <= $this->low_stock_threshold;
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'variant_id');
    }
}
