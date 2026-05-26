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
            $table->foreignId('shipping_destination_id')->nullable()->constrained('shipping_destinations')->nullOnDelete()->after('payment_method');
            $table->decimal('shipping_cost', 12, 2)->default(0)->after('shipping_destination_id');
            $table->text('shipping_address')->nullable()->after('shipping_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->dropForeign(['shipping_destination_id']);
            $table->dropColumn(['shipping_destination_id', 'shipping_cost', 'shipping_address']);
        });
    }
};
