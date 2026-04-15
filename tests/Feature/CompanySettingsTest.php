<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Filament\Pages\CompanySettingsPage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createRole(Company $company, Roles $role, array $permissions = []): \Spatie\Permission\Models\Role
    {
        setPermissionsTeamId($company->id);
        
        $roleModel = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => $role->value,
            'company_id' => $company->id,
            'guard_name' => 'web',
        ]);

        if (!empty($permissions)) {
            $roleModel->syncPermissions($permissions);
        }

        return $roleModel;
    }

    public function test_only_company_level_users_with_permission_can_access_settings_page()
    {
        $company = Company::factory()->create(['is_active' => true]);
        
        // Create Company Admin role and give it 'update_setting' permission
        $this->createRole($company, Roles::COMPANY_ADMIN, ['update_setting']);

        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => null, // Company level
            'is_active' => true,
        ]);
        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        $this->actingAs($admin);

        $this->get(CompanySettingsPage::getUrl())
            ->assertSuccessful();
    }

    public function test_store_level_users_are_forbidden_from_accessing_settings_page()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store = Store::factory()->create(['company_id' => $company->id]);
        
        // Create Store Manager role and give it 'update_setting' permission
        $this->createRole($company, Roles::STORE_MANAGER, ['update_setting', 'view_any_setting']);

        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id, // Store level
            'is_active' => true,
        ]);
        
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $this->actingAs($manager);

        // Should be forbidden even if they have the permission, because they are store-level
        $this->get(CompanySettingsPage::getUrl())
            ->assertStatus(403);
    }

    public function test_company_admin_can_update_advanced_settings()
    {
        $company = Company::factory()->create(['is_active' => true]);
        
        $this->createRole($company, Roles::COMPANY_ADMIN, ['update_setting']);

        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => null,
            'is_active' => true,
        ]);
        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        $this->actingAs($admin);

        Livewire::test(CompanySettingsPage::class)
            ->fillForm([
                'tax_label' => 'Custom VAT',
                'tax_is_inclusive' => true,
                'rounding_rule' => \App\Enums\RoundingRule::NEAREST_050->value,
                'currency_symbol' => 'EGP',
                'currency_position' => \App\Enums\CurrencyPosition::LEFT->value,
                'decimal_precision' => 3,
                'invoice_prefix' => 'MARKET-',
                'invoice_next_number' => 500,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $company->refresh();

        $this->assertEquals('Custom VAT', $company->tax_label);
        $this->assertTrue($company->tax_is_inclusive);
        $this->assertEquals(\App\Enums\RoundingRule::NEAREST_050, $company->rounding_rule);
        $this->assertEquals(3, $company->decimal_precision);
        $this->assertEquals('MARKET-', $company->invoice_prefix);
        $this->assertEquals(500, $company->invoice_next_number);
    }
}
