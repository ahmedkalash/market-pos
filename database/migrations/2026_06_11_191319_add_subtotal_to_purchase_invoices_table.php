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
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0.00)->after('vendor_invoice_ref');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0.00)->after('vendor_credit_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn('subtotal');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn('subtotal');
        });
    }
};
