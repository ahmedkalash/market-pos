<?php

namespace Tests\Feature;

use App\Enums\AdjustmentReason;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    private User $user;

    private Store $store;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventoryService::class);

        $company = Company::factory()->create(['is_active' => true]);
        $this->store = Store::factory()->create(['company_id' => $company->id]);

        $this->user = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
            'is_active' => true,
        ]);

        $category = ProductCategory::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
        ]);

        $taxClass = TaxClass::factory()->create([
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'tax_class_id' => $taxClass->id,
        ]);

        $uom = UnitOfMeasure::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
        ]);

        $this->variant = ProductVariant::factory()->withStock(100)->create([
            'product_id' => $product->id,
            'uom_id' => $uom->id,
        ]);

        $this->actingAs($this->user);
    }

    public function test_adjust_stock_add_increases_quantity(): void
    {
        $movement = $this->service->adjustStock(
            variant: $this->variant,
            quantity: 25,
            direction: MovementDirection::In,
            reason: AdjustmentReason::StocktakeCorrection,
            notes: 'Found extra during count',
        );

        $this->variant->refresh();

        $this->assertEquals(125.0, (float) $this->variant->quantity);
        $this->assertInstanceOf(InventoryMovement::class, $movement);
        $this->assertEquals(MovementType::AdjustmentAdd, $movement->type);
        $this->assertEquals(25.0, (float) $movement->quantity);
        $this->assertEquals(MovementDirection::In, $movement->direction);
        $this->assertEquals(AdjustmentReason::StocktakeCorrection, $movement->reason);
        $this->assertEquals('Found extra during count', $movement->notes);
        $this->assertEquals($this->user->id, $movement->user_id);
        $this->assertEquals($this->store->id, $movement->store_id);
    }

    public function test_adjust_stock_subtract_decreases_quantity(): void
    {
        $movement = $this->service->adjustStock(
            variant: $this->variant,
            quantity: 10,
            direction: MovementDirection::Out,
            reason: AdjustmentReason::Damaged,
            notes: '10 bottles broken during stocking',
        );

        $this->variant->refresh();

        $this->assertEquals(90.0, (float) $this->variant->quantity);
        $this->assertEquals(MovementType::AdjustmentSub, $movement->type);
        $this->assertEquals(0.0, (float) $movement->quantity_in ?? 0);
        $this->assertEquals(10.0, (float) $movement->quantity);
        $this->assertEquals(MovementDirection::Out, $movement->direction);
        $this->assertEquals(AdjustmentReason::Damaged, $movement->reason);
    }

    public function test_adjust_stock_subtract_exceeding_stock_throws_exception(): void
    {
        $this->expectException(InsufficientStockException::class);

        $this->service->adjustStock(
            variant: $this->variant,
            quantity: 150,
            direction: MovementDirection::Out,
            reason: AdjustmentReason::Shrinkage,
        );

        // Verify quantity was NOT changed (transaction rolled back)
        $this->variant->refresh();
        $this->assertEquals(100.0, (float) $this->variant->quantity);
    }

    public function test_set_opening_stock_creates_correct_movement(): void
    {
        $zeroVariant = ProductVariant::factory()->withStock(0)->create([
            'product_id' => $this->variant->product_id,
        ]);

        $movement = $this->service->setOpeningStock(
            variant: $zeroVariant,
            quantity: 500,
            notes: 'Initial stock load for go-live',
        );

        $zeroVariant->refresh();

        $this->assertEquals(500.0, (float) $zeroVariant->quantity);
        $this->assertEquals(MovementType::OpeningStock, $movement->type);
        $this->assertEquals(AdjustmentReason::OpeningStock, $movement->reason);
        $this->assertEquals(500.0, (float) $movement->quantity);
        $this->assertEquals(MovementDirection::In, $movement->direction);
    }

    public function test_record_movement_stores_correct_store_id(): void
    {
        $movement = $this->service->recordMovement(
            variant: $this->variant,
            type: MovementType::StockIn,
            quantity: 50,
        );

        $this->assertEquals($this->store->id, $movement->store_id);
    }

    public function test_movement_is_immutable_no_updated_at(): void
    {
        $movement = $this->service->adjustStock(
            variant: $this->variant,
            quantity: 5,
            direction: MovementDirection::In,
            reason: AdjustmentReason::StocktakeCorrection,
        );

        // Verify the model has no updated_at
        $this->assertFalse($movement->usesTimestamps());
        $this->assertArrayNotHasKey('updated_at', $movement->getAttributes());
    }

    public function test_transaction_rolls_back_on_insufficient_stock(): void
    {
        $initialCount = InventoryMovement::count();

        try {
            $this->service->adjustStock(
                variant: $this->variant,
                quantity: 999,
                direction: MovementDirection::Out,
                reason: AdjustmentReason::Shrinkage,
            );
        } catch (InsufficientStockException) {
            // Expected
        }

        // No movement should have been created
        $this->assertEquals($initialCount, InventoryMovement::count());

        // Quantity should be unchanged
        $this->variant->refresh();
        $this->assertEquals(100.0, (float) $this->variant->quantity);
    }

    public function test_multiple_movements_accumulate_correctly(): void
    {
        $this->service->adjustStock($this->variant, 20, MovementDirection::In, AdjustmentReason::StocktakeCorrection);
        $this->service->adjustStock($this->variant, 5, MovementDirection::Out, AdjustmentReason::Damaged);
        $this->service->adjustStock($this->variant, 10, MovementDirection::Out, AdjustmentReason::Expired);
        $this->service->adjustStock($this->variant, 3, MovementDirection::In, AdjustmentReason::StocktakeCorrection);

        $this->variant->refresh();

        // 100 + 20 - 5 - 10 + 3 = 108
        $this->assertEquals(108.0, (float) $this->variant->quantity);

        // Verify movement count
        $this->assertEquals(4, $this->variant->inventoryMovements()->count());
    }

    public function test_variant_has_inventory_movements_relationship(): void
    {
        $this->service->adjustStock($this->variant, 10, MovementDirection::In, AdjustmentReason::OpeningStock);

        $movements = $this->variant->inventoryMovements;

        $this->assertCount(1, $movements);
        $this->assertInstanceOf(InventoryMovement::class, $movements->first());
    }
}
