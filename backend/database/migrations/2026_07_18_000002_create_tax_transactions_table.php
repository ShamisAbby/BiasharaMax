<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('tax_config_id')->constrained('business_tax_configurations')->restrictOnDelete();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->string('transaction_type'); // output/input
            $table->decimal('taxable_amount', 14, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->date('transaction_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_transactions');
    }
};
