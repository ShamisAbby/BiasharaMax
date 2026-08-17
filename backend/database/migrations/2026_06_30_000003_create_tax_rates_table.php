<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->decimal('rate', 5, 2);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
