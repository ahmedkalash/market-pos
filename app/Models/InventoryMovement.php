<?php

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable inventory movement record.
 *
 * Each row represents a single stock change event. Records are append-only
 * and must never be updated or deleted through application code.
 */
class InventoryMovement extends Model
{
    use BelongsToStore;

    /** @var bool Disable updated_at — movements are immutable. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'variant_id',
        'store_id',
        'user_id',
        'type',
        'quantity',
        'direction',
        'reason',
        'notes',
        'reference_type',
        'reference_id',
        'unit_cost',
        'total_cost',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'direction' => MovementDirection::class,
            'reason' => AdjustmentReason::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /**
     * Polymorphic reference to the source document (Invoice, PurchaseOrder, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
