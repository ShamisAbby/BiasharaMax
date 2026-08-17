<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->string('name');
            $table->string('slug');
            $table->string('input_type', 20)->default('select');
            $table->boolean('is_variant_attribute')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unique(['business_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
