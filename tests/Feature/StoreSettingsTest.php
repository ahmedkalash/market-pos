<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Filament\Pages\StoreSettingsPage;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
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

    public function test_only_store_level_users_with_permission_can_access_store_settings_page()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store = Store::factory()->create(['company_id' => $company->id]);

        $this->createRole($company, Roles::STORE_MANAGER, ['manage_store_settings']);

        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $this->actingAs($manager);

        $this->get(StoreSettingsPage::getUrl())
            ->assertSuccessful();
    }

    public function test_company_level_users_are_forbidden_from_store_settings_page()
    {
        $company = Company::factory()->create(['is_active' => true]);

        $this->createRole($company, Roles::COMPANY_ADMIN, ['manage_store_settings']);

        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => null, // Company level
            'is_active' => true,
        ]);
        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        $this->actingAs($admin);

        // Filament often returns 404 if a page is hidden from a user's panel manifest via canAccess()
        $response = $this->get(StoreSettingsPage::getUrl());
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_store_settings_do_not_automatically_inherit_from_company()
    {
        $company = Company::factory()->create([
            'is_active' => true,
            'whatsapp_number' => 'COMPANY-WA',
        ]);
        $store = Store::factory()->create([
            'company_id' => $company->id,
            'whatsapp_number' => null,
        ]);

        $this->assertNull($store->getSetting('whatsapp_number'));
        $this->assertNotEquals('COMPANY-WA', $store->getSetting('whatsapp_number'));
    }

    public function test_store_manager_can_save_independent_settings()
    {
        $company = Company::factory()->create(['is_active' => true]);
        $store = Store::factory()->create(['company_id' => $company->id]);

        $this->createRole($company, Roles::STORE_MANAGER, ['manage_store_settings']);

        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $this->actingAs($manager);

        Livewire::test(StoreSettingsPage::class)
            ->fillForm([
                'whatsapp_number' => '987654',
                'receipt_header' => 'Branch Header',
                'receipt_show_logo' => '0',
                'receipt_show_vat_number' => '0',
                'receipt_show_address' => '0',
                'timezone' => 'Africa/Cairo',
                'locale' => 'ar',
                'working_hours' => [
                    [
                        'day' => 'monday',
                        'from' => '08:00',
                        'to' => '17:00',
                    ],
                    [
                        'day' => 'tuesday',
                        'from' => '09:00',
                        'to' => '18:00',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $store->refresh();

        $this->assertEquals('987654', $store->whatsapp_number);
        $this->assertEquals('Branch Header', $store->receipt_header);
        $this->assertFalse($store->receipt_show_logo);
        $this->assertIsArray($store->working_hours);
        $this->assertEquals('monday', $store->working_hours[0]['day']);
        $this->assertEquals('08:00', $store->working_hours[0]['from']);
        $this->assertEquals('17:00', $store->working_hours[0]['to']);
    }

    public function test_store_manager_can_manually_pull_from_company()
    {
        $company = Company::factory()->create([
            'is_active' => true,
            'whatsapp_number' => 'COMPANY-WA',
        ]);
        $store = Store::factory()->create(['company_id' => $company->id]);

        $this->createRole($company, Roles::STORE_MANAGER, ['manage_store_settings']);

        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $this->actingAs($manager);

        Livewire::test(StoreSettingsPage::class)
            // Initial state should be empty/null even if company has value
            ->assertFormSet(['whatsapp_number' => null])
            // Perform manual pull
            ->callFormComponentAction('whatsapp_number', 'pull_whatsapp_number')
            ->assertFormSet(['whatsapp_number' => 'COMPANY-WA']);
    }

    public function test_store_manager_can_manually_pull_working_hours_from_company()
    {
        $workingHours = [
            ['day' => 'saturday', 'from' => '10:00', 'to' => '23:00'],
        ];

        $company = Company::factory()->create([
            'is_active' => true,
            'working_hours' => $workingHours,
        ]);
        $store = Store::factory()->create([
            'company_id' => $company->id,
            'working_hours' => null,
        ]);

        $this->createRole($company, Roles::STORE_MANAGER, ['manage_store_settings']);

        /** @var User $manager */
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);
        $manager->assignRole(Roles::STORE_MANAGER->value);

        $this->actingAs($manager);

        Livewire::test(StoreSettingsPage::class)
            // Initial state should be empty even if company has value
            ->assertSet('data.working_hours', [])
            // Perform manual pull
            ->callFormComponentAction('working_hours', 'pull_from_company')
            ->assertSet('data.working_hours', function ($value) use ($workingHours) {
                $actual = array_values($value);

                return $actual === $workingHours;
            });
    }
}
