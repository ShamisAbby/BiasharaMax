<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('currency_id');
            $table->boolean('is_primary')->default(false);
            $table->decimal('exchange_rate_override', 14, 6)->nullable();
            $table->date('rate_as_of')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();
            $table->unique(['business_id', 'currency_id']);
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_currencies');
    }
};
