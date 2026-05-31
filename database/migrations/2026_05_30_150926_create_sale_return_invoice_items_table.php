<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_return_invoice_id')
                ->constrained('sale_return_invoices')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            // Link to the original sale invoice item for proration and validation
            $table->foreignId('original_item_id')
                ->constrained('sale_invoice_items')
                ->restrictOnDelete();

            $table->decimal('quantity', 12, 3);

            // Financial snapshots from the original sale item
            $table->decimal('unit_price', 12, 4);
            $table->decimal('unit_discount_amount', 12, 4)->default(0);

            // Proration — this item's per-unit share of the invoice global discount
            $table->decimal('prorated_global_discount', 12, 4)->default(0);

            // The actual per-unit refund after all discount layers
            $table->decimal('effective_unit_refund', 12, 4)->default(0);

            // effective_unit_refund × quantity
            $table->decimal('item_refund_total', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_invoice_items');
    }
};
