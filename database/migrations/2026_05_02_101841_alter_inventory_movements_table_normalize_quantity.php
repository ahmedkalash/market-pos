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
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->after('type');
            $table->string('direction')->after('quantity'); // 'in' or 'out'
            
            $table->dropColumn(['quantity_in', 'quantity_out']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'direction']);
            
            $table->decimal('quantity_in', 15, 4)->default(0)->after('type');
            $table->decimal('quantity_out', 15, 4)->default(0)->after('quantity_in');
        });
    }
};
