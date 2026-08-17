<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('warehouse_id');
            $table->string('count_number');
            $table->string('status', 20)->default('draft');
            $table->uuid('started_by')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->unique(['business_id', 'count_number']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_counts');
    }
};
