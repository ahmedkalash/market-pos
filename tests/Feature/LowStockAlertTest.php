<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Notifications\NotifyLowStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $storeManager;

    private User $companyAdmin;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $company = Company::factory()->create();
        setPermissionsTeamId($company->id);

        $store = Store::factory()->create(['company_id' => $company->id]);

        // Setup Roles
        $adminRole = Role::create(['name' => Roles::COMPANY_ADMIN->value, 'company_id' => $company->id]);
        $managerRole = Role::create(['name' => Roles::STORE_MANAGER->value, 'company_id' => $company->id]);

        $this->companyAdmin = User::factory()->create(['company_id' => $company->id]);
        $this->companyAdmin->assignRole($adminRole);

        $this->storeManager = User::factory()->create(['company_id' => $company->id, 'store_id' => $store->id]);
        $this->storeManager->assignRole($managerRole);

        $category = ProductCategory::factory()->create(['company_id' => $company->id, 'store_id' => $store->id]);
        $taxClass = TaxClass::factory()->create(['company_id' => $company->id]);
        $uom = UnitOfMeasure::factory()->create(['company_id' => $company->id, 'store_id' => $store->id]);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'tax_class_id' => $taxClass->id,
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'uom_id' => $uom->id,
            'quantity' => 10,
            'low_stock_threshold' => 5,
            'low_stock_alert_fired' => false,
        ]);
    }

    public function test_it_sends_notification_when_stock_hits_threshold(): void
    {
        $this->variant->update(['quantity' => 4]);

        Notification::assertSentTo(
            [$this->storeManager, $this->companyAdmin],
            NotifyLowStock::class
        );

        $this->variant->refresh();
        $this->assertTrue($this->variant->low_stock_alert_fired);
    }

    public function test_it_does_not_send_duplicate_notifications(): void
    {
        $this->variant->update(['quantity' => 4]);

        // Clear notifications sent so far
        Notification::fake();

        $this->variant->update(['quantity' => 3]);

        Notification::assertNothingSent();
    }

    public function test_it_resets_fired_flag_when_stock_is_replenished(): void
    {
        // First, trigger the alert
        $this->variant->update(['quantity' => 4, 'low_stock_alert_fired' => true]);

        // Replenish
        $this->variant->update(['quantity' => 10]);

        $this->variant->refresh();
        $this->assertFalse($this->variant->low_stock_alert_fired);

        // Drop again to verify it triggers again
        Notification::fake();
        $this->variant->update(['quantity' => 2]);

        Notification::assertSentTo(
            [$this->storeManager, $this->companyAdmin],
            NotifyLowStock::class
        );
    }

    public function test_it_does_not_trigger_if_no_threshold_is_set(): void
    {
        $this->variant->update(['low_stock_threshold' => null]);
        $this->variant->update(['quantity' => 0]);

        Notification::assertNothingSent();
    }
}
