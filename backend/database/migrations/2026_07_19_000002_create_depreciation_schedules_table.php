<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciation_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('period_date'); // first day of month
            $table->decimal('depreciation_amount', 14, 2);
            $table->decimal('accumulated_depreciation', 14, 2);
            $table->decimal('book_value', 14, 2);
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('status')->default('pending'); // pending/posted
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_schedules');
    }
};
