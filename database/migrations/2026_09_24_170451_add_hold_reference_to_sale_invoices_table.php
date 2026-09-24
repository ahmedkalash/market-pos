<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->string('hold_reference', 255)->nullable()->after('invoice_number')->index();
            $table->index(['store_id', 'status', 'created_at'], 'sale_invoices_store_status_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->dropIndex('sale_invoices_store_status_created_idx');
            $table->dropIndex(['hold_reference']);
            $table->dropColumn('hold_reference');
        });
    }
};
