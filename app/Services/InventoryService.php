<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Central service for all inventory stock changes.
 *
 * Every stock mutation MUST go through this service to ensure:
 * 1. Atomic writes (ledger + cache in one transaction)
 * 2. Pessimistic locking (prevents race conditions)
 * 3. Complete audit trail (every change is recorded)
 *
 * NEVER modify product_variants.quantity directly.
 */
class InventoryService
{
    /**
     * @return InventoryService
     */
    public static function make(): InventoryService
    {
        return app(static::class);
    }

    /**
     * Record an inventory movement and update the cached quantity.
     *
     * Uses SELECT ... FOR UPDATE on the variant row to prevent concurrent
     * modifications from causing quantity drift.
     */
    public function recordMovement(
        ProductVariant $variant,
        MovementType $type,
        float $quantityIn = 0,
        float $quantityOut = 0,
        ?AdjustmentReason $reason = null,
        ?string $notes = null,
        ?Model $reference = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($variant, $type, $quantityIn, $quantityOut, $reason, $notes, $reference) {
            // Pessimistic lock: prevent concurrent reads of stale quantity
            /** @var ProductVariant $lockedVariant */
            $lockedVariant = ProductVariant::query()
                ->lockForUpdate()
                ->findOrFail($variant->id);

            // Validate sufficient stock for outbound movements
            if ($quantityOut > 0 && $lockedVariant->quantity < $quantityOut) {
                throw new InsufficientStockException(
                    variant: $lockedVariant,
                    requested: $quantityOut,
                    available: (float) $lockedVariant->quantity,
                );
            }

            // Resolve store_id from the variant's parent product
            $storeId = $lockedVariant->product->store_id;

            // 1. Insert immutable movement record
            $movement = InventoryMovement::create([
                'variant_id' => $lockedVariant->id,
                'store_id' => $storeId,
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'reason' => $reason,
                'notes' => $notes,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_at' => now(),
            ]);

            // 2. Update cached quantity on the variant (atomic increment/decrement)
            $netChange = $quantityIn - $quantityOut;
            if ($netChange > 0) {
                $lockedVariant->increment('quantity', $netChange);
            } elseif ($netChange < 0) {
                $lockedVariant->decrement('quantity', abs($netChange));
            }

            return $movement;
        });
    }

    /**
     * Adjust stock (manual add or subtract).
     *
     * @param  float  $quantity  Positive to add, negative to subtract.
     */
    public function adjustStock(
        ProductVariant $variant,
        float $quantity,
        AdjustmentReason $reason,
        ?string $notes = null,
    ): InventoryMovement {
        $type = $quantity >= 0 ? MovementType::AdjustmentAdd : MovementType::AdjustmentSub;

        return $this->recordMovement(
            variant: $variant,
            type: $type,
            quantityIn: max(0, $quantity),
            quantityOut: max(0, -$quantity),
            reason: $reason,
            notes: $notes,
        );
    }

    /**
     * Set opening stock for a variant.
     */
    public function setOpeningStock(
        ProductVariant $variant,
        float $quantity,
        ?string $notes = null,
    ): InventoryMovement {
        return $this->recordMovement(
            variant: $variant,
            type: MovementType::OpeningStock,
            quantityIn: $quantity,
            reason: AdjustmentReason::OpeningStock,
            notes: $notes,
        );
    }
}
