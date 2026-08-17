<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Chart of Accounts — the backbone every JournalLine posts against.
     * Seeded with system-default accounts per business (see
     * ChartOfAccountsService::seedDefaults()); business owners can add
     * custom accounts but system-default ones cannot be deleted, only
     * deactivated, since historical journal lines may reference them.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('parent_account_id')->nullable();

            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('type', 20);
            $table->string('subtype', 80)->nullable();
            $table->string('normal_balance', 6);

            $table->boolean('is_system_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'type']);
            $table->index(['business_id', 'is_active']);
        });

        // Self-referencing FK added after table creation: Postgres won't
        // reliably resolve a self-reference declared inline in the same
        // CREATE TABLE statement.
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('parent_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
