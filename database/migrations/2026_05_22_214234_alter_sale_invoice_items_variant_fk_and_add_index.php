<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two improvements to sale_invoice_items:
 *
 * 1. Change product_variant_id FK from CASCADE DELETE to RESTRICT.
 *    This prevents silent destruction of financial history if a product
 *    variant is ever deleted. The system will now throw a DB error instead.
 *
 * 2. Add a composite index on (sale_invoice_id, product_variant_id) to
 *    speed up duplicate-checking queries and line-item joins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            // 1. Drop the existing cascading FK
            $table->dropForeign(['product_variant_id']);

            // 2. Re-add with RESTRICT — prevents deletion of variants
            //    referenced in any invoice line item.
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->restrictOnDelete();

            // 3. Composite index for performance
            $table->index(['sale_invoice_id', 'product_variant_id'], 'sale_invoice_items_si_pv_index');
        });
    }

    public function down(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            $table->dropIndex('sale_invoice_items_si_pv_index');
            $table->dropForeign(['product_variant_id']);
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();
        });
    }
};
