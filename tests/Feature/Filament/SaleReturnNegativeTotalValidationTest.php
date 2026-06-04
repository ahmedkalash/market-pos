<?php

namespace Tests\Feature\Filament;

use App\Enums\ExtraItemActionType;
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
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class SaleReturnNegativeTotalValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Store $store;
    protected SaleInvoice $invoice;
    protected SaleInvoiceItem $invoiceItem;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->store = Store::factory()->create(['company_id' => $this->company->id]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $role = SpatieRole::create(['name' => Roles::SUPER_ADMIN->value]);
        $this->user->assignRole($role);

        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $this->invoice = SaleInvoice::factory()->create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'status' => SaleInvoiceStatus::Finalized,
            'return_status' => SaleInvoiceReturnStatus::None,
        ]);

        $product = Product::factory()->create(['store_id' => $this->store->id]);
        $this->variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->invoiceItem = SaleInvoiceItem::factory()->create([
            'sale_invoice_id' => $this->invoice->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);
    }

    protected function getBaseFormData(float $itemRefundTotal = 100): array
    {
        return [
            'store_id' => $this->store->id,
            'customer_id' => $this->invoice->customer_id,
            'return_reason' => 'Defective',
            'items_refund_total' => $itemRefundTotal,
            'items' => [
                [
                    'original_item_id' => $this->invoiceItem->id,
                    'product_variant_id' => $this->variant->id,
                    'product_name' => 'Test Product',
                    'barcodes' => ['123456789'],
                    'max_returnable' => 1,
                    'quantity' => 1,
                    'unit_price' => 100,
                    'unit_discount_amount' => 0,
                    'prorated_global_discount' => 0,
                    'effective_unit_refund' => $itemRefundTotal,
                    'item_refund_total' => $itemRefundTotal,
                    'line_total' => $itemRefundTotal,
                ],
            ],
        ];
    }

    public function test_cannot_create_when_single_subtraction_exceeds_budget(): void
    {
        $this->actingAs($this->user);

        $formData = $this->getBaseFormData(100);
        $formData['extraItems'] = [
            [
                'name' => 'Restocking Fee',
                'action_type' => ExtraItemActionType::Subtraction->value,
                'amount' => 150, // Exceeds 100
            ],
        ];
        $formData['total_refund_amount'] = -50;

        Livewire::test(CreateSaleReturnInvoice::class)
            ->set('data.invoice_number_input', $this->invoice->invoice_number)
            ->fillForm($formData)
            ->call('create')
            ->assertHasFormErrors(['total_refund_amount']);
    }

    public function test_cannot_create_when_combined_subtractions_exceed_budget(): void
    {
        $this->actingAs($this->user);

        $formData = $this->getBaseFormData(100);
        $formData['extraItems'] = [
            [
                'name' => 'Restocking Fee 1',
                'action_type' => ExtraItemActionType::Subtraction->value,
                'amount' => 60,
            ],
            [
                'name' => 'Restocking Fee 2',
                'action_type' => ExtraItemActionType::Subtraction->value,
                'amount' => 60,
            ],
        ];
        $formData['total_refund_amount'] = -20; // 100 - 60 - 60 = -20

        Livewire::test(CreateSaleReturnInvoice::class)
            ->set('data.invoice_number_input', $this->invoice->invoice_number)
            ->fillForm($formData)
            ->call('create')
            ->assertHasFormErrors(['total_refund_amount']);
    }

    public function test_can_create_when_subtraction_equals_total(): void
    {
        $this->actingAs($this->user);

        $formData = $this->getBaseFormData(100);
        $formData['extraItems'] = [
            [
                'name' => 'Full Restocking Fee',
                'action_type' => ExtraItemActionType::Subtraction->value,
                'amount' => 100,
            ],
        ];
        $formData['total_refund_amount'] = 0; // Exactly 0 should be allowed

        Livewire::test(CreateSaleReturnInvoice::class)
            ->set('data.invoice_number_input', $this->invoice->invoice_number)
            ->fillForm($formData)
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_can_create_when_addition_then_subtraction_within_combined_budget(): void
    {
        $this->actingAs($this->user);

        $formData = $this->getBaseFormData(100);
        $formData['extraItems'] = [
            [
                'name' => 'Goodwill Credit',
                'action_type' => ExtraItemActionType::Addition->value,
                'amount' => 50, // Total becomes 150
            ],
            [
                'name' => 'Restocking Fee',
                'action_type' => ExtraItemActionType::Subtraction->value,
                'amount' => 120, // 150 - 120 = 30. Valid because of the addition, though it exceeds the base 100
            ],
        ];
        $formData['total_refund_amount'] = 30; 

        Livewire::test(CreateSaleReturnInvoice::class)
            ->set('data.invoice_number_input', $this->invoice->invoice_number)
            ->fillForm($formData)
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
