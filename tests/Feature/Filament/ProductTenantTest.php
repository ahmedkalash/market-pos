<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $companyUser;
    private Store $store;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->company = Company::factory()->create();
        $this->store = Store::factory()->create(['company_id' => $this->company->id]);
        
        $this->companyUser = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);
        $this->companyUser->givePermissionTo(['create_product', 'update_product']);
    }

    public function test_variant_inherits_tenant_ids_from_product_during_creation()
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);
        
        $uom = UnitOfMeasure::factory()->create([
            'company_id' => $this->company->id,
        ]);

        Livewire::actingAs($this->companyUser)
            ->test(VariantsRelationManager::class, [
                'ownerRecord' => $product,
                'pageClass' => EditProduct::class,
            ])
        ->callTableAction('create', data: [
            'name_en' => 'Test Variant',
            'name_ar' => 'Test Variant AR',
            'uom_id' => $uom->id,
            'purchase_price' => 10,
            'retail_price' => 20,
            'quantity' => 0,
            'barcodes' => [],
            'variant_attributes' => [],
        ])
        ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'name_en' => 'Test Variant',
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);
    }

    public function test_product_query_is_scoped_to_user_company()
    {
        $otherCompany = Company::factory()->create();
        $otherStore = Store::factory()->create(['company_id' => $otherCompany->id]);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        Product::factory()->create([
            'company_id' => $otherCompany->id,
            'store_id' => $otherStore->id,
        ]);

        $this->actingAs($this->companyUser);
        
        $products = Product::all();
        
        $this->assertCount(1, $products);
        $this->assertEquals($this->company->id, $products->first()->company_id);
    }

    public function test_product_query_is_scoped_to_user_store()
    {
        $otherStore = Store::factory()->create(['company_id' => $this->company->id]);
        
        $storeUser = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);
        $storeUser->givePermissionTo(['create_product', 'update_product']);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
        ]);

        $this->actingAs($storeUser);
        
        $products = Product::all();
        
        $this->assertCount(1, $products);
        $this->assertEquals($this->store->id, $products->first()->store_id);
    }

    public function test_product_variant_query_is_scoped_to_user_company()
    {
        $otherCompany = Company::factory()->create();
        $otherStore = Store::factory()->create(['company_id' => $otherCompany->id]);

        $product1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product1->id,
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $product2 = Product::factory()->create([
            'company_id' => $otherCompany->id,
            'store_id' => $otherStore->id,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product2->id,
            'company_id' => $otherCompany->id,
            'store_id' => $otherStore->id,
        ]);

        $this->actingAs($this->companyUser);
        
        $variants = ProductVariant::all();
        
        $this->assertCount(1, $variants);
        $this->assertEquals($this->company->id, $variants->first()->company_id);
    }

    public function test_product_variant_query_is_scoped_to_user_store()
    {
        $otherStore = Store::factory()->create(['company_id' => $this->company->id]);
        
        $storeUser = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $product1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product1->id,
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $product2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product2->id,
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
        ]);

        $this->actingAs($storeUser);
        
        $variants = ProductVariant::all();
        
        $this->assertCount(1, $variants);
        $this->assertEquals($this->store->id, $variants->first()->store_id);
    }

    public function test_product_auto_assigns_company_and_store_id()
    {
        $storeUser = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $this->actingAs($storeUser);

        $product = Product::factory()->create([
            'company_id' => null,
            'store_id' => null,
        ]);

        $this->assertEquals($this->company->id, $product->company_id);
        $this->assertEquals($this->store->id, $product->store_id);
    }

    public function test_product_variant_auto_assigns_company_and_store_id()
    {
        $storeUser = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $this->actingAs($storeUser);

        $variant = ProductVariant::factory()->create([
            'company_id' => null,
            'store_id' => null,
        ]);

        $this->assertEquals($this->company->id, $variant->company_id);
        $this->assertEquals($this->store->id, $variant->store_id);
    }
}
