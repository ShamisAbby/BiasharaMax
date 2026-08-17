<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('warehouse_id');
            $table->uuid('parent_location_id')->nullable();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->index('warehouse_id');
        });

        Schema::table('warehouse_locations', function (Blueprint $table) {
            $table->foreign('parent_location_id')->references('id')->on('warehouse_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};
