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
            $table->foreignId('company_id')->after('id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('store_id')->after('company_id')->constrained('stores')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['store_id']);
            $table->dropColumn(['company_id', 'store_id']);
        });
    }
};
