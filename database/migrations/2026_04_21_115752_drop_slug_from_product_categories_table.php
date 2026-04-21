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
        Schema::table('product_categories', function (Blueprint $table) {
            // First, add a regular index to company_id so it continues to support the foreign key
            $table->index('company_id');
            
            // Now we can drop the unique index and the slug column
            $table->dropUnique('product_categories_company_id_slug_unique');
            $table->dropColumn('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name_ar')->nullable();
        });
    }
};
