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
        Schema::table('sale_return_invoice_items', function (Blueprint $table) {
            $table->renameColumn('prorated_global_discount', 'unit_prorated_global_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_return_invoice_items', function (Blueprint $table) {
            $table->renameColumn('unit_prorated_global_discount', 'prorated_global_discount');
        });
    }
};
