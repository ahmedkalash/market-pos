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
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->decimal('unit_discount_amount', 15, 4)->default(0)->after('unit_cost');
            $table->decimal('unit_prorated_global_discount', 15, 4)->default(0)->after('unit_discount_amount');
            $table->decimal('effective_unit_refund', 15, 4)->default(0)->after('unit_prorated_global_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit_discount_amount',
                'unit_prorated_global_discount',
                'effective_unit_refund',
            ]);
        });
    }
};
