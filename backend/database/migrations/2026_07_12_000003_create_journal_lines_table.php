<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('journal_entry_id');
            $table->uuid('account_id');

            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->text('description')->nullable();

            $table->uuid('customer_id')->nullable();
            $table->uuid('supplier_id')->nullable();

            $table->unsignedInteger('line_number')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            // An account that has ever been posted to can never be hard-deleted.
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();

            $table->index(['business_id', 'account_id']);
            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
