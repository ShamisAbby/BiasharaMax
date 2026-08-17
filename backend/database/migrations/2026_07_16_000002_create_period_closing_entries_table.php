<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_closing_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('financial_period_id');
            $table->uuid('closing_journal_entry_id');
            $table->string('closing_type', 30);
            $table->uuid('posted_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('financial_period_id')->references('id')->on('financial_periods')->cascadeOnDelete();
            $table->foreign('closing_journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_closing_entries');
    }
};
