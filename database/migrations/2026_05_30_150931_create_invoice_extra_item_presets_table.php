<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_extra_item_presets', function (Blueprint $table) {
            $table->id();

            // Tenant scope
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->enum('action_type', ['addition', 'subtraction']);
            $table->decimal('amount', 12, 2);
            $table->boolean('is_refundable')->default(false);
            $table->enum('invoice_type', ['sale_invoice', 'sale_return', 'purchase_invoice', 'purchase_return']);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_extra_item_presets');
    }
};
