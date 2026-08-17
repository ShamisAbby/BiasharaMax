<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('bank_account_id');

            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('statement_balance', 14, 2);
            $table->decimal('book_balance', 14, 2);
            $table->decimal('difference', 14, 2);

            $table->string('status', 20)->default('draft');
            $table->uuid('reconciled_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->cascadeOnDelete();
            $table->foreign('reconciled_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['business_id', 'bank_account_id']);
            $table->index(['bank_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
