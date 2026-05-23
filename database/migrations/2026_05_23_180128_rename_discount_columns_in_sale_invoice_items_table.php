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
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            $table->renameColumn('discount_amount', 'unit_discount_amount');
            $table->renameColumn('discount_value', 'line_total_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            $table->renameColumn('unit_discount_amount', 'discount_amount');
            $table->renameColumn('line_total_discount', 'discount_value');
        });
    }
};
