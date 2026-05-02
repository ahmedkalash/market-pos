<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\MovementDirection;
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
        float $quantity,
        ?AdjustmentReason $reason = null,
        ?string $notes = null,
        ?Model $reference = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($variant, $type, $quantity, $reason, $notes, $reference) {
            // Pessimistic lock: prevent concurrent reads of stale quantity
            /** @var ProductVariant $lockedVariant */
            $lockedVariant = ProductVariant::query()
                ->lockForUpdate()
                ->findOrFail($variant->id);

            // Resolve direction from type
            $direction = $type->getDirection();

            // Validate sufficient stock for outbound movements
            if ($direction === MovementDirection::Out && $lockedVariant->quantity < abs($quantity)) {
                throw new InsufficientStockException(
                    variant: $lockedVariant,
                    requested: abs($quantity),
                    available: (float) $lockedVariant->quantity,
                );
            }

            // Resolve store_id and cost basis from the variant
            $storeId = $lockedVariant->product->store_id;
            $unitCost = (float) $lockedVariant->purchase_price;

            // total_cost is signed (Positive for additions, Negative for subtractions).
            $multiplier = ($direction === MovementDirection::In) ? 1 : -1;
            $totalCost = abs($quantity) * $unitCost * $multiplier;

            // 1. Insert immutable movement record
            $movement = InventoryMovement::create([
                'variant_id' => $lockedVariant->id,
                'store_id' => $storeId,
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity' => abs($quantity),
                'direction' => $direction,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reason' => $reason,
                'notes' => $notes,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_at' => now(),
            ]);

            // 2. Update cached quantity on the variant (atomic increment/decrement)
            if ($direction === MovementDirection::In) {
                $lockedVariant->increment('quantity', abs($quantity));
            } else {
                $lockedVariant->decrement('quantity', abs($quantity));
            }

            return $movement;
        });
    }

    /**
     * Adjust stock (manual add or subtract).
     */
    public function adjustStock(
        ProductVariant $variant,
        float $quantity,
        MovementDirection $direction,
        AdjustmentReason $reason,
        ?string $notes = null,
    ): InventoryMovement {
        $type = ($direction === MovementDirection::In)
            ? MovementType::AdjustmentAdd
            : MovementType::AdjustmentSub;

        return $this->recordMovement(
            variant: $variant,
            type: $type,
            quantity: abs($quantity),
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
            quantity: abs($quantity),
            reason: AdjustmentReason::OpeningStock,
            notes: $notes,
        );
    }
}
