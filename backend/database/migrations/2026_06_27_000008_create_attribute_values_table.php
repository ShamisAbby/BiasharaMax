<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attribute_id');
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->unique(['attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};
