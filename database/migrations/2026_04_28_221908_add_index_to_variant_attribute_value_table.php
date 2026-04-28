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
        Schema::table('variant_attribute_value', function (Blueprint $table) {
            $table->index('attribute_value_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variant_attribute_value', function (Blueprint $table) {
            $table->dropIndex(['attribute_value_id']);
        });
    }
};
