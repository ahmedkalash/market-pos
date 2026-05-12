<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();

            // Tenant scope
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Vendor is optional — standalone returns may not have a known vendor
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();

            // Link back to the original invoice — nullable for standalone returns.
            // restrictOnDelete: DB prevents deleting an invoice that has any linked return,
            // adding a second layer of protection on top of the app-level canDelete() guard.
            $table->foreignId('original_invoice_id')
                ->nullable()
                ->constrained('purchase_invoices')
                ->restrictOnDelete();

            // Document identity
            $table->string('return_number');
            $table->string('vendor_credit_ref')->nullable();

            // Return metadata
            $table->text('return_reason');  // required — audit integrity
            $table->enum('status', ['draft', 'finalized'])->default('draft');

            // Financials — tax-agnostic MVP (same pattern as purchase_invoices)
            $table->decimal('total_before_tax', 12, 2)->default(0);
            $table->decimal('total_tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->date('returned_at');

            // Finalization audit
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();

            // Creation audit
            $table->foreignId('created_by')->constrained('users')->nullOnDelete();

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
        Schema::dropIfExists('purchase_returns');
    }
};
