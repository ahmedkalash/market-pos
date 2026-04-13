<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createRole(string $name, int $companyId): void
    {
        setPermissionsTeamId($companyId);
        Role::firstOrCreate([
            'name' => $name,
            'company_id' => $companyId,
            'guard_name' => 'web',
        ]);
    }

    public function test_store_manager_is_scoped_to_their_store()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store1 = Store::factory()->create(['company_id' => $company->id]);
        $store2 = Store::factory()->create(['company_id' => $company->id]);

        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store1->id,
            'is_active' => true,
        ]);

        $this->createRole(Roles::STORE_MANAGER->value, $company->id);
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $userInStore1 = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store1->id,
        ]);

        $userInStore2 = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store2->id,
        ]);

        $this->actingAs($manager);

        // StoreManager should only see users in their store
        $users = User::all();

        $this->assertTrue($users->contains($userInStore1));
        $this->assertFalse($users->contains($userInStore2));
        $this->assertTrue($users->contains($manager));
    }

    public function test_company_accountant_sees_all_stores_in_their_company()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store1 = Store::factory()->create(['company_id' => $company->id]);
        $store2 = Store::factory()->create(['company_id' => $company->id]);

        /** @var User $accountant */
        $accountant = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => null, // Company level
            'is_active' => true,
        ]);

        $this->createRole(Roles::ACCOUNTANT->value, $company->id);
        $accountant->assignRole(Roles::ACCOUNTANT->value);

        $userInStore1 = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store1->id,
        ]);

        $userInStore2 = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store2->id,
        ]);

        $this->actingAs($accountant);

        // Accountant should see all users in the company
        $users = User::all();

        $this->assertTrue($users->contains($userInStore1));
        $this->assertTrue($users->contains($userInStore2));
        $this->assertTrue($users->contains($accountant));
    }

    public function test_super_admin_sees_everything()
    {
        $company1 = Company::factory()->create(['is_active' => true]);
        $company2 = Company::factory()->create(['is_active' => true]);

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create([
            'company_id' => null,
            'store_id' => null,
            'is_active' => true,
        ]);
        $superAdmin->assignRole(Roles::SUPER_ADMIN->value);

        $user1 = User::factory()->create(['company_id' => $company1->id]);
        $user2 = User::factory()->create(['company_id' => $company2->id]);

        $this->actingAs($superAdmin);

        $users = User::all();

        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
        $this->assertTrue($users->contains($superAdmin));
    }
}
