<?php

namespace Tests\Feature\Filament;

use App\Actions\CreateDefaultCompanyRolesAction;
use App\Enums\Roles;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
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

    public function test_tenant_company_admin_can_view_customers(): void
    {
        $company = Company::factory()->create(['is_active' => true]);
        $admin = $this->createAdminForCompany($company);

        $this->actingAs($admin);

        $customers = Customer::factory()->count(3)->create(['company_id' => $company->id]);

        // Other tenant's customer
        $otherCompany = Company::factory()->create(['is_active' => true]);
        $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords($customers)
            ->assertCanNotSeeTableRecords([$otherCustomer]);
    }

    public function test_can_create_customer(): void
    {
        $company = Company::factory()->create(['is_active' => true]);
        $admin = $this->createAdminForCompany($company);

        $this->actingAs($admin);

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '1234567890',
                'tax_number' => '987654321',
                'address' => '123 Main St',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
    }

    public function test_can_edit_customer(): void
    {
        $company = Company::factory()->create(['is_active' => true]);
        $admin = $this->createAdminForCompany($company);

        $this->actingAs($admin);

        $customer = Customer::factory()->create(['company_id' => $company->id]);

        Livewire::test(EditCustomer::class, [
            'record' => $customer->getRouteKey(),
        ])
            ->fillForm([
                'name' => 'Jane Doe',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Jane Doe',
        ]);
    }
}
