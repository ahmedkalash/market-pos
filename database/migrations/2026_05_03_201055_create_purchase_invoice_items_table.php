<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 3); // supports weighted items e.g. 2.500 KG
            $table->decimal('unit_cost', 10, 4); // cost per unit BEFORE tax
            $table->decimal('subtotal', 12, 2); // quantity × unit_cost
            $table->decimal('tax_rate', 5, 2)->default(0); // snapshot from tax_class at time of receiving
            $table->decimal('tax_amount', 12, 2)->default(0); // subtotal × (tax_rate / 100)
            $table->decimal('line_total', 12, 2); // subtotal + tax_amount
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_invoice_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
