<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE sequences MODIFY COLUMN type ENUM('purchase_invoice', 'purchase_return', 'sale_invoice', 'sale_return') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE sequences MODIFY COLUMN type ENUM('purchase_invoice', 'purchase_return', 'sale_invoice') NOT NULL");
    }
};
