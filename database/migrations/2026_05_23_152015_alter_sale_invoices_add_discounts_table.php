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
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable()->after('status');
            $table->decimal('discount_amount', 12, 4)->nullable()->after('discount_type');
            $table->decimal('total_discount_amount', 12, 2)->default(0)->after('discount_amount');
        });

        Schema::table('sale_invoice_items', function (Blueprint $table) {
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable()->after('price_type');
            $table->decimal('discount_amount', 12, 4)->nullable()->after('discount_type');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_amount', 'discount_value']);
        });

        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_amount', 'total_discount_amount']);
        });
    }
};
