<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_invoices', function (Blueprint $table) {
            $table->id();

            // Tenant scope
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Customer is optional — copied from original invoice
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Link back to the original sale invoice — always required for sale returns.
            // restrictOnDelete: DB prevents deleting an invoice that has any linked return.
            $table->foreignId('original_invoice_id')
                ->constrained('sale_invoices')
                ->restrictOnDelete();

            // Document identity
            $table->string('return_number');

            // Return metadata
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->text('return_reason');

            // Financials — refund breakdown
            $table->decimal('items_refund_total', 12, 2)->default(0);
            $table->decimal('extra_items_total', 12, 2)->default(0);
            $table->decimal('total_refund_amount', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->date('returned_at');

            // Finalization audit
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();

            // Creation audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Unique return number per company
            $table->unique(['company_id', 'return_number']);

            // Fast lookups by original invoice
            $table->index(['company_id', 'original_invoice_id']);

            // Reporting by date range within a store
            $table->index(['store_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_invoices');
    }
};
