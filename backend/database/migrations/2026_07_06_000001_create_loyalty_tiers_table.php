<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');

            $table->string('name');
            $table->string('slug');
            $table->decimal('minimum_spend', 14, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->text('benefits_description')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();

            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'minimum_spend']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_tiers');
    }
};
