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
            $table->decimal('subtotal', 12, 2)->default(0)->change();
            $table->decimal('line_total', 12, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            // Revert by dropping the default values
            $table->decimal('subtotal', 12, 2)->default(null)->change();
            $table->decimal('line_total', 12, 2)->default(null)->change();
        });
    }
};
