<?php

namespace Tests\Feature\Filament;

use App\Enums\Roles;
use App\Enums\SaleInvoiceReturnStatus;
use App\Enums\SaleInvoiceStatus;
use App\Filament\Resources\SaleReturnInvoices\Pages\CreateSaleReturnInvoice;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnInvoice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class SaleReturnInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->company = Company::factory()->create();
        $this->store = Store::factory()->create(['company_id' => $this->company->id]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        app(\App\Actions\CreateDefaultCompanyRolesAction::class)->execute($this->company);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->company->id);

        $this->user->assignRole(\App\Enums\Roles::COMPANY_ADMIN->value);
    }

    public function test_create_sets_return_number_and_created_by(): void
    {
        $this->actingAs($this->user);

        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        // Given a finalized, returnable sale invoice
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'status' => SaleInvoiceStatus::Finalized,
            'return_status' => SaleInvoiceReturnStatus::None,
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id
        ]);
        $variant = ProductVariant::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id
        ]);

        $invoiceItem = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 100,
            'line_total' => 200,
        ]);

        Livewire::test(CreateSaleReturnInvoice::class)
            ->fillForm([
                'invoice_number_input' => $invoice->invoice_number,
                'store_id' => $this->store->id,
                'customer_id' => $invoice->customer_id,
                'return_reason' => 'Defective',
                'items' => [
                    [
                        'original_item_id' => $invoiceItem->id,
                        'product_variant_id' => $variant->id,
                        'max_returnable' => 2,
                        'quantity' => 1,
                        'unit_price' => 100,
                        'unit_discount_amount' => 0,
                        'unit_prorated_global_discount' => 0,
                        'effective_unit_refund' => 100,
                        'item_refund_total' => 100,
                        'line_total' => 100,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $returnInvoice = SaleReturnInvoice::query()->first();

        $this->assertNotNull($returnInvoice);
        $this->assertEquals($this->user->id, $returnInvoice->created_by);
        $this->assertNotNull($returnInvoice->return_number);
    }

    public function test_cannot_create_return_against_draft_invoice(): void
    {
        $this->actingAs($this->user);

        // Given a draft sale invoice
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Draft,
            'return_status' => SaleInvoiceReturnStatus::None,
        ]);

        Livewire::test(CreateSaleReturnInvoice::class)
            ->fillForm([
                'invoice_number_input' => $invoice->invoice_number,
            ])
            ->assertFormSet([
                'original_invoice_id' => null, // Should not be populated
            ])
            ->assertNotified();
    }

    public function test_cannot_create_return_against_fully_returned_invoice(): void
    {
        $this->actingAs($this->user);

        // Given a fully returned sale invoice
        $invoice = SaleInvoice::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'status' => SaleInvoiceStatus::Finalized,
            'return_status' => SaleInvoiceReturnStatus::FullyReturned,
        ]);

        Livewire::test(CreateSaleReturnInvoice::class)
            ->fillForm([
                'invoice_number_input' => $invoice->invoice_number,
            ])
            ->assertFormSet([
                'original_invoice_id' => null, // Should not be populated
            ])
            ->assertNotified();
    }
}
