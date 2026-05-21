<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->enum('type', ['purchase_invoice', 'purchase_return', 'sale_invoice'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->enum('type', ['purchase_invoice', 'purchase_return'])->change();
        });
    }
};
