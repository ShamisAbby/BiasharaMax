<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Country-level only — seeded from the real ISO-3166 list. Regions
     * and cities from the spec are deliberately not modeled as separate
     * tables: BiasharaMax's `Business.country` is already a plain string,
     * and seeding a real, complete world cities dataset is its own
     * project, not a side effect of this sprint. Flagged honestly rather
     * than half-built.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 2)->unique();
            $table->string('name');
            $table->string('default_currency_code', 3)->nullable();
            $table->string('phone_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
