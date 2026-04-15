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
        Schema::table('companies', function (Blueprint $table) {
            // Localization
            $table->string('timezone')->default('Africa/Cairo')->after('locale');
            $table->string('date_format')->default('Y-m-d')->after('timezone');
            $table->string('time_format')->default('H:i')->after('date_format');

            // Financial & Number Formatting
            $table->string('currency_symbol')->default('ج.م')->after('currency');
            $table->enum('currency_position', ['left', 'right'])->default('left')->after('currency_symbol');
            $table->string('thousand_separator')->default(',')->after('currency_position');
            $table->string('decimal_separator')->default('.')->after('thousand_separator');
            $table->integer('decimal_precision')->default(2)->after('decimal_separator');

            // Taxation
            $table->string('tax_label')->default('VAT')->after('vat_rate');
            $table->boolean('tax_is_inclusive')->default(true)->after('tax_label');

            // Invoicing & Receipts
            $table->string('invoice_prefix')->default('INV-')->after('receipt_show_logo');
            $table->integer('invoice_next_number')->default(1)->after('invoice_prefix');
            $table->boolean('receipt_show_vat_number')->default(true)->after('invoice_next_number');
            $table->boolean('receipt_show_address')->default(true)->after('receipt_show_vat_number');

            // Rounding Rules
            $table->enum('rounding_rule', ['none', 'nearest_025', 'nearest_050', 'nearest_100'])->default('none')->after('receipt_show_address');

            // Compliance
            $table->boolean('enable_zatca_qr')->default(false)->after('rounding_rule');

            // WhatsApp for receipt
            $table->string('whatsapp_number')->nullable()->after('enable_zatca_qr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'date_format',
                'time_format',
                'currency_symbol',
                'currency_position',
                'thousand_separator',
                'decimal_separator',
                'decimal_precision',
                'tax_label',
                'tax_is_inclusive',
                'invoice_prefix',
                'invoice_next_number',
                'receipt_show_vat_number',
                'receipt_show_address',
                'rounding_rule',
                'enable_zatca_qr',
                'whatsapp_number',
            ]);
        });
    }
};
