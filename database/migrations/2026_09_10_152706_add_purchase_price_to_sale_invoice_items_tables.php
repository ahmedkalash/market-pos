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
            $table->decimal('purchase_price', 15, 4)->nullable()->after('unit_price');
        });

        Schema::table('sale_return_invoice_items', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 4)->nullable()->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });

        Schema::table('sale_return_invoice_items', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
