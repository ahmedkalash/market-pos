<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires re-declaring all existing ENUM values when adding a new one.
        DB::statement("ALTER TABLE sequences MODIFY COLUMN type ENUM('purchase_invoice', 'purchase_return') NOT NULL");
    }

    public function down(): void
    {
        // Remove 'purchase_return' — only safe while no rows carry that value.
        DB::statement("ALTER TABLE sequences MODIFY COLUMN type ENUM('purchase_invoice') NOT NULL");
    }
};
