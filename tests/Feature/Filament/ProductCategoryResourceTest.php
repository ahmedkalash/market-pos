<?php

namespace Tests\Feature\Filament;

use App\Actions\CreateDefaultCompanyRolesAction;
use App\Enums\Roles;
use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Models\Company;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function createAdminForCompany(Company $company): User
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        app(CreateDefaultCompanyRolesAction::class)->execute($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        return $admin;
    }

    public function test_tenant_company_admin_can_view_product_categories()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $admin = $this->createAdminForCompany($company);

        $this->actingAs($admin);

        $categories = ProductCategory::factory()->count(3)->create(['company_id' => $company->id]);

        // Other tenant's category
        $otherCompany = Company::factory()->create(['is_active' => true]);
        $otherCategory = ProductCategory::factory()->create(['company_id' => $otherCompany->id]);

        Livewire::test(ListProductCategories::class)
            ->assertCanSeeTableRecords($categories)
            ->assertCanNotSeeTableRecords([$otherCategory]);
    }

    public function test_can_create_product_category()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $admin = $this->createAdminForCompany($company);

        $this->actingAs($admin);

        Livewire::test(CreateProductCategory::class)
            ->fillForm([
                'name_en' => 'Test Category',
                'name_ar' => 'Test Category AR',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_categories', [
            'name_en' => 'Test Category',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
    }

    public function test_can_edit_product_category_and_adjacency_list_parent()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $admin = $this->createAdminForCompany($company);

        $this->actingAs($admin);

        $parent = ProductCategory::factory()->create(['company_id' => $company->id]);
        $child = ProductCategory::factory()->create(['company_id' => $company->id]);

        Livewire::test(EditProductCategory::class, [
            'record' => $child->getRouteKey(),
        ])
            ->fillForm([
                'name_en' => 'Updated Child',
                'parent_id' => $parent->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_categories', [
            'id' => $child->id,
            'name_en' => 'Updated Child',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_store_manager_cannot_view_product_categories()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store = \App\Models\Store::factory()->create(['company_id' => $company->id]);
        
        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);

        app(CreateDefaultCompanyRolesAction::class)->execute($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $manager->assignRole(Roles::STORE_MANAGER->value);

        $this->actingAs($manager);

        Livewire::test(ListProductCategories::class)
            ->assertForbidden();
    }
}
