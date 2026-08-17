<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');

            $table->unsignedSmallInteger('fiscal_year');
            $table->string('period_name', 50);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('open');
            $table->boolean('is_year_end')->default(false);

            $table->uuid('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['business_id', 'period_start', 'period_end']);
            $table->index(['business_id', 'fiscal_year']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_periods');
    }
};
