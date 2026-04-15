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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->text('receipt_header')->nullable()->after('working_hours');
            $table->text('receipt_footer')->nullable()->after('receipt_header');
            $table->boolean('receipt_show_logo')->default(true)->after('receipt_footer');
            $table->boolean('receipt_show_vat_number')->default(true)->after('receipt_show_logo');
            $table->boolean('receipt_show_address')->default(true)->after('receipt_show_vat_number');
            $table->string('timezone')->default('Africa/Cairo')->after('receipt_show_address');
            $table->string('locale')->default('ar')->after('timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_number',
                'receipt_header',
                'receipt_footer',
                'receipt_show_logo',
                'receipt_show_vat_number',
                'receipt_show_address',
                'timezone',
                'locale',
            ]);
        });
    }
};
