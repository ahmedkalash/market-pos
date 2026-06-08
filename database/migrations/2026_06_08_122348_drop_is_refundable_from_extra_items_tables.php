<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'sale_invoice_extra_items',
            'sale_return_extra_items',
            'purchase_invoice_extra_items',
            'purchase_return_extra_items',
            'invoice_extra_item_presets',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('is_refundable');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'sale_invoice_extra_items',
            'sale_return_extra_items',
            'purchase_invoice_extra_items',
            'purchase_return_extra_items',
            'invoice_extra_item_presets',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('is_refundable')->default(false);
            });
        }
    }
};
