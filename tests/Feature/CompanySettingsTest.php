<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Filament\Pages\CompanySettingsPage;
use App\Models\Company;
use App\Models\User;
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

    public function test_company_admin_can_access_company_settings_page()
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

        $this->get(CompanySettingsPage::getUrl())
            ->assertSuccessful();
    }

    public function test_company_admin_can_save_working_hours()
    {
        $company = Company::factory()->create(['is_active' => true]);

        $this->createRole($company, Roles::COMPANY_ADMIN, [
            'update_setting',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'store_id' => null,
            'is_active' => true,
        ]);
        $admin->assignRole(Roles::COMPANY_ADMIN->value);

        $this->actingAs($admin);

        $workingHours = [
            [
                'day' => 'monday',
                'from' => '08:00:00',
                'to' => '17:00:00',
            ],
            [
                'day' => 'tuesday',
                'from' => '09:00:00',
                'to' => '18:00:00',
            ],
        ];

        Livewire::test(CompanySettingsPage::class)
            ->fillForm([
                'working_hours' => $workingHours,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $company->refresh();

        $this->assertIsArray($company->working_hours);
        $this->assertCount(2, $company->working_hours);
        $this->assertEquals('monday', $company->working_hours[0]['day']);
        $this->assertEquals('08:00:00', $company->working_hours[0]['from']);
        $this->assertEquals('17:00:00', $company->working_hours[0]['to']);
    }
}
