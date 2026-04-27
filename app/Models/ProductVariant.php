<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'uom_id',
        'name_en',
        'name_ar',
        'price',
        'price_is_negotiable',
        'minimum_price',
        'quantity',
        'low_stock_threshold',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'minimum_price' => 'decimal:2',
            'quantity' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'price_is_negotiable' => 'boolean',
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
}
