<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('bank_account_id');
            $table->uuid('journal_entry_id')->nullable();

            $table->date('transaction_date');
            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            $table->string('reconciliation_status', 20)->default('unreconciled');
            $table->timestamp('reconciled_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->index(['business_id', 'bank_account_id']);
            $table->index(['bank_account_id', 'transaction_date']);
            $table->index(['bank_account_id', 'reconciliation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
