<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No soft deletes here by design: posted journal entries are an
     * accounting-integrity record and must never be deleted, only
     * reversed or voided (see JournalPostingService::reverse()/void()).
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');

            $table->string('entry_number');
            $table->date('entry_date');
            $table->string('status', 20)->default('draft');
            $table->string('type', 10)->default('manual');
            $table->text('description')->nullable();
            $table->text('memo')->nullable();

            $table->uuid('source_id')->nullable();
            $table->string('source_type')->nullable();

            $table->uuid('reversed_journal_entry_id')->nullable();
            $table->uuid('reversal_of_id')->nullable();

            $table->uuid('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();

            $table->unique(['business_id', 'entry_number']);
            $table->index(['business_id', 'entry_date']);
            $table->index(['business_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        // Self-referencing FKs added after table creation: Postgres won't
        // reliably resolve a self-reference declared inline in the same
        // CREATE TABLE statement.
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('reversed_journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('reversal_of_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
