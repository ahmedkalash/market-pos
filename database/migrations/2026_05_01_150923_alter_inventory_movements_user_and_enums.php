<?php

use App\Enums\AdjustmentReason;
use App\Enums\MovementType;
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
            // 1. user_id: nullable + nullOnDelete (preserve movements when user is deleted)
            $table->foreignId('user_id')->nullable()->change();
            $table->dropForeign(['user_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // 2. type: string → enum
        Schema::table('inventory_movements', function (Blueprint $table) {
            $values = array_column(MovementType::cases(), 'value');
            $table->enum('type', $values)->change();
        });

        // 3. reason: string → enum
        Schema::table('inventory_movements', function (Blueprint $table) {
            $values = array_column(AdjustmentReason::cases(), 'value');
            $table->enum('reason', $values)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->change();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('type')->change();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('reason')->nullable()->change();
        });
    }
};
