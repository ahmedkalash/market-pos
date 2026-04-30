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
        Schema::table('product_variants', function (Blueprint $table) {
            // Rename existing price columns (safe — preserves data)
            $table->renameColumn('price', 'retail_price');
            $table->renameColumn('minimum_price', 'min_retail_price');
            $table->renameColumn('price_is_negotiable', 'retail_is_price_negotiable');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            // Purchase / Cost price — required for margin reporting
            $table->decimal('purchase_price', 12, 2)->after('retail_is_price_negotiable')->default(0);

            // Wholesale pricing — all nullable; only relevant when wholesale_enabled = true
            $table->boolean('wholesale_enabled')->after('purchase_price')->default(false);
            $table->decimal('wholesale_price', 12, 2)->after('wholesale_enabled')->nullable();
            $table->boolean('wholesale_is_price_negotiable')->after('wholesale_price')->default(false)->nullable();
            $table->decimal('min_wholesale_price', 12, 2)->after('wholesale_is_price_negotiable')->nullable();

            // 0 = no quantity requirement (cashier decides); > 0 = auto-apply threshold
            $table->decimal('wholesale_qty_threshold', 12, 3)->after('min_wholesale_price')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price',
                'wholesale_enabled',
                'wholesale_price',
                'wholesale_is_price_negotiable',
                'min_wholesale_price',
                'wholesale_qty_threshold',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->renameColumn('retail_price', 'price');
            $table->renameColumn('min_retail_price', 'minimum_price');
            $table->renameColumn('retail_is_price_negotiable', 'price_is_negotiable');
        });
    }
};
