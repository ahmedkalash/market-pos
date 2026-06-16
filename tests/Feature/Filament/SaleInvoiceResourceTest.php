<?php

namespace Tests\Feature\Filament;

use App\Actions\CreateDefaultCompanyRolesAction;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\Roles;
use App\Filament\Resources\SaleInvoices\Pages\CreateSaleInvoice;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SaleInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Store $store;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $company = Company::factory()->create(['is_active' => true]);
        $this->store = Store::factory()->create(['company_id' => $company->id]);

        $this->cashier = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
            'is_active' => true,
        ]);

        app(CreateDefaultCompanyRolesAction::class)->execute($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        // Assign Cashier role which has permission to create sale invoices
        $this->cashier->assignRole(Roles::CASHIER->value);

        $category = ProductCategory::factory()->create([
            'company_id' => $company->id,
            'store_id' => $this->store->id,
        ]);

        $taxClass = TaxClass::factory()->create([
            'company_id' => $company->id,
        ]);

        $product = Product::factory()->create([
            'company_id' => $company->id,
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
            'retail_price' => 15.00,
            'wholesale_enabled' => true,
            'wholesale_price' => 12.00,
            'wholesale_qty_threshold' => 5.0,
        ]);

        $this->actingAs($this->cashier);
    }

    public function test_cannot_save_invoice_when_wholesale_quantity_below_threshold(): void
    {
        Livewire::test(CreateSaleInvoice::class)
            ->fillForm([
                'store_id' => $this->store->id,
                'payment_method' => PaymentMethod::Cash->value,
                'items' => [
                    'item1' => [
                        'product_variant_id' => $this->variant->id,
                        'price_type' => PriceType::Wholesale->value,
                        'quantity' => 3,
                        'unit_price' => 12.00,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['items.item1.quantity']);
    }

    public function test_can_save_invoice_when_wholesale_quantity_equals_or_exceeds_threshold(): void
    {
        Livewire::test(CreateSaleInvoice::class)
            ->fillForm([
                'store_id' => $this->store->id,
                'payment_method' => PaymentMethod::Cash->value,
                'items' => [
                    'item1' => [
                        'product_variant_id' => $this->variant->id,
                        'price_type' => PriceType::Wholesale->value,
                        'quantity' => 5,
                        'unit_price' => 12.00,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sale_invoices', [
            'store_id' => $this->store->id,
            'created_by' => $this->cashier->id,
        ]);
    }
}
