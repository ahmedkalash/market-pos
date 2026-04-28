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
        Schema::table('attributes', function (Blueprint $table) {
            $table->foreignId('store_id')->after('company_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->foreignId('store_id')->after('company_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('store_id')->after('company_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });

        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};
