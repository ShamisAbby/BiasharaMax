<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('base_unit_id')->nullable();
            $table->string('name');
            $table->string('symbol', 10);
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->string('status', 20)->default('active');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unique(['business_id', 'symbol']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreign('base_unit_id')->references('id')->on('units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
