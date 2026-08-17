<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('from_warehouse_id');
            $table->uuid('to_warehouse_id');
            $table->string('transfer_number');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('dispatched_by')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->unique(['business_id', 'transfer_number']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
