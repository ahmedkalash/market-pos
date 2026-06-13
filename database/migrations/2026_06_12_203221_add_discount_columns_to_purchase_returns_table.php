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
        if (! Schema::hasColumn('purchase_returns', 'discount_type')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                $table->enum('discount_type', ['fixed', 'percentage'])->nullable()->after('store_id');
                $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_type');
                $table->decimal('global_discount_amount', 15, 2)->default(0)->after('discount_amount');
                $table->decimal('grand_total_discount', 15, 2)->default(0)->after('global_discount_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn([
                'discount_type',
                'discount_amount',
                'global_discount_amount',
                'grand_total_discount',
            ]);
        });
    }
};
