<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('budgeted_amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'account_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
    }
};
