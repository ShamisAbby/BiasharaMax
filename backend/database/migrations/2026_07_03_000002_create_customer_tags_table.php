<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');

            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();

            $table->unique(['business_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tags');
    }
};
