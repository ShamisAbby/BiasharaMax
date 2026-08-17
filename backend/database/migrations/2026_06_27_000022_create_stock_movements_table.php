<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The immutable inventory ledger. Every stock-quantity change in the
     * platform — regardless of which module triggers it — is recorded here
     * and never updated or deleted afterward. No `updated_at`, no soft
     * deletes: this table is append-only by design.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('branch_id');
            $table->uuid('warehouse_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('product_batch_id')->nullable();

            $table->string('type', 30);
            $table->string('direction', 3);
            $table->decimal('quantity', 14, 3);
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();

            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('product_batch_id')->references('id')->on('product_batches')->nullOnDelete();

            $table->index(['business_id', 'created_at']);
            $table->index(['product_id', 'warehouse_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
