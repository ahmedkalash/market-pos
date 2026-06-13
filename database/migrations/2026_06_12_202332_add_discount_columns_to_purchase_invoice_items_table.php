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
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable()->after('unit_cost');
            $table->decimal('unit_discount_amount', 12, 4)->nullable()->after('discount_type');
            $table->decimal('line_total_discount', 12, 2)->default(0)->after('unit_discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'unit_discount_amount', 'line_total_discount']);
        });
    }
};
