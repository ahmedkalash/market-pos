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

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_manager_can_only_manage_subordinate_staff()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store = Store::factory()->create(['company_id' => $company->id]);

        setPermissionsTeamId($company->id);
        Role::create(['name' => Roles::STORE_MANAGER->value, 'company_id' => $company->id, 'guard_name' => 'web']);
        Role::create(['name' => Roles::CASHIER->value, 'company_id' => $company->id, 'guard_name' => 'web']);

        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $cashier = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
        ]);
        $cashier->assignRole(Roles::CASHIER->value);

        $otherManager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
        ]);
        $otherManager->assignRole(Roles::STORE_MANAGER->value);

        // Manager can manage cashier
        $this->assertTrue($cashier->isManageableBy($manager));

        // Manager cannot manage themselves (for restricted fields/roles)
        // Note: isManageableBy logic allows them to manage themselves if they have permission,
        // but specific field disables handle the role change.
        // Actually, let's check the isManageableBy implementation.
        // It says Store Managers can only manage CASHIER and STOCK_CLERK.
        $this->assertFalse($manager->isManageableBy($manager));

        // Manager cannot manage another manager
        $this->assertFalse($otherManager->isManageableBy($manager));
    }

    public function test_company_admin_can_manage_all_company_users()
    {
        $company = Company::factory()->create(['is_active' => true]);
        setPermissionsTeamId($company->id);
        Role::create(['name' => Roles::COMPANY_ADMIN->value, 'company_id' => $company->id, 'guard_name' => 'web']);
        Role::create(['name' => Roles::STORE_MANAGER->value, 'company_id' => $company->id, 'guard_name' => 'web']);
        Role::create(['name' => Roles::CASHIER->value, 'company_id' => $company->id, 'guard_name' => 'web']);

        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $cashier = User::factory()->create(['company_id' => $company->id]);
        $cashier->assignRole(Roles::CASHIER->value);

        $otherCompanyUser = User::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->assertTrue($manager->isManageableBy($admin));
        $this->assertTrue($cashier->isManageableBy($admin));
        $this->assertFalse($otherCompanyUser->isManageableBy($admin));
    }

    public function test_super_admin_can_manage_anyone()
    {
        $company = Company::factory()->create(['is_active' => true]);
        setPermissionsTeamId($company->id);
        Role::create(['name' => Roles::COMPANY_ADMIN->value, 'company_id' => $company->id, 'guard_name' => 'web']);

        /** @var User $super */
        $super = User::factory()->create(['company_id' => null]);
        $super->assignRole(Roles::SUPER_ADMIN->value);

        $otherAdmin = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $otherAdmin->assignRole(Roles::COMPANY_ADMIN->value);

        $this->assertTrue($otherAdmin->isManageableBy($super));
    }
}
