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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type'); // MovementType enum
            $table->decimal('quantity_in', 10, 3)->default(0);
            $table->decimal('quantity_out', 10, 3)->default(0);

            $table->string('reason')->nullable(); // AdjustmentReason enum
            $table->text('notes')->nullable();

            // Polymorphic reference (Invoice, PurchaseOrder, etc.)
            $table->nullableMorphs('reference');

            // Immutable: only created_at, no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Composite indexes for common query patterns
            $table->index(['variant_id', 'created_at']);
            $table->index(['store_id', 'type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
