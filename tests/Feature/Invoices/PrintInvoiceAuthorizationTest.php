<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceType;
use App\Models\SaleInvoice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PrintInvoiceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_unauthenticated_user_cannot_print_invoices()
    {
        $response = $this->get(route('invoice.print', [
            'type' => InvoiceType::SaleInvoice->value,
            'id' => 1,
        ]));

        $response->assertRedirect();
    }

    public function test_invalid_invoice_type_returns_404()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('invoice.print', [
            'type' => 'invalid_type',
            'id' => 1,
        ]));

        $response->assertNotFound();
    }

    public function test_user_without_permission_cannot_print_sale_invoice()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $store = Store::factory()->create(['company_id' => $user->company_id]);
        $invoice = SaleInvoice::factory()->create(['store_id' => $store->id, 'company_id' => $user->company_id]);

        $response = $this->actingAs($user)->get(route('invoice.print', [
            'type' => InvoiceType::SaleInvoice->value,
            'id' => $invoice->id,
        ]));

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_print_sale_invoice()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $user->givePermissionTo('view_sale_invoice');

        $store = Store::factory()->create(['company_id' => $user->company_id]);
        $invoice = SaleInvoice::factory()->create(['store_id' => $store->id, 'company_id' => $user->company_id]);

        $response = $this->actingAs($user)->get(route('invoice.print', [
            'type' => InvoiceType::SaleInvoice->value,
            'id' => $invoice->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('print.thermal.invoice');
    }

    public function test_user_cannot_print_invoice_from_another_company()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $user->givePermissionTo('view_sale_invoice');

        $otherCompanyUser = User::factory()->create();
        $otherStore = Store::factory()->create(['company_id' => $otherCompanyUser->company_id]);

        // This will be scoped by the global scope implicitly for standard requests,
        // but let's test if the controller properly handles it via findOrFail() scoping.
        $invoice = SaleInvoice::factory()->create(['store_id' => $otherStore->id, 'company_id' => $otherCompanyUser->company_id]);

        $response = $this->actingAs($user)->get(route('invoice.print', [
            'type' => InvoiceType::SaleInvoice->value,
            'id' => $invoice->id,
        ]));

        // Since it's findOrFail and global scopes are active, it should be 404
        $response->assertNotFound();
    }
}
