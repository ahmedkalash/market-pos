<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_return_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->constrained()
                ->restrictOnDelete();

            // Nullable FK to the specific original line for traceability & quantity validation.
            // restrictOnDelete: once set, this audit link must never be silently severed.
            // In practice this never fires — the invoice itself is already restricted from deletion.
            $table->foreignId('original_item_id')
                ->nullable()
                ->constrained('purchase_invoice_items')
                ->restrictOnDelete();

            // Quantity being returned
            $table->decimal('quantity', 10, 3);

            // Snapshot of cost at time of return
            $table->decimal('unit_cost', 10, 4);
            $table->decimal('subtotal', 12, 2)->default(0);

            // Tax snapshot — frozen at 0 for MVP, same as invoices
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);

            // Per-line notes (e.g. "2 broken, 1 expired")
            $table->string('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
