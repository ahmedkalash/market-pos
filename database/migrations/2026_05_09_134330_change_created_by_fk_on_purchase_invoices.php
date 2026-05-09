<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')
                ->nullable()
                ->change()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')
                ->nullable(false)
                ->change()
                ->constrained('users')
                ->cascadeOnDelete();
        });
    }
};
