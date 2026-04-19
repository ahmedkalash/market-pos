<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Filament\Resources\Stores\Pages\EditStore;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createRole(Company $company, Roles $role, array $permissions = []): Role
    {
        setPermissionsTeamId($company->id);

        $roleModel = Role::firstOrCreate([
            'name' => $role->value,
            'company_id' => $company->id,
            'guard_name' => 'web',
        ]);

        if (! empty($permissions)) {
            $roleModel->syncPermissions($permissions);
        }

        return $roleModel;
    }

    public function test_company_admin_can_access_store_resource()
    {
        $company = Company::factory()->create(['is_active' => true]);

        $this->createRole($company, Roles::COMPANY_ADMIN, ['view_any_store', 'view_store', 'update_store']);

        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        $this->actingAs($admin);

        $this->get(StoreResource::getUrl())
            ->assertSuccessful();
    }

    public function test_store_manager_cannot_access_store_resource()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store = Store::factory()->create(['company_id' => $company->id]);

        $this->createRole($company, Roles::STORE_MANAGER, ['update_store']);

        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $this->actingAs($manager);

        $this->get(StoreResource::getUrl())
            ->assertForbidden();
    }

    public function test_admin_can_update_store_via_resource()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store = Store::factory()->create(['company_id' => $company->id]);

        $this->createRole($company, Roles::COMPANY_ADMIN, ['view_any_store', 'view_store', 'update_store']);

        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        $this->actingAs($admin);

        Livewire::test(EditStore::class, [
            'record' => $store->getRouteKey(),
        ])
            ->fillForm([
                'name_en' => 'Updated Store Name',
                'whatsapp_number' => '123456789',
                'images' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'name_en' => 'Updated Store Name',
            'whatsapp_number' => '123456789',
        ]);
    }

    public function test_admin_can_pull_from_company_in_resource_context()
    {
        $company = Company::factory()->create([
            'is_active' => true,
            'whatsapp_number' => 'COMPANY-WA-123',
        ]);
        $store = Store::factory()->create(['company_id' => $company->id]);

        $this->createRole($company, Roles::COMPANY_ADMIN, ['view_any_store', 'view_store', 'update_store']);

        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        $this->actingAs($admin);

        Livewire::test(EditStore::class, [
            'record' => $store->getRouteKey(),
        ])
            ->assertFormSet(['whatsapp_number' => $store->whatsapp_number])
            ->callFormComponentAction('whatsapp_number', 'pull_whatsapp_number')
            ->assertFormSet(['whatsapp_number' => 'COMPANY-WA-123']);
    }
}
