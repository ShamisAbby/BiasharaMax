<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polymorphic across both auth identities (tenant `users` and
     * `platform_users`) so it works without adding a parallel table
     * for each guard.
     */
    public function up(): void
    {
        Schema::create('account_lockouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('lockable_type', 20);
            $table->uuid('lockable_id');
            $table->text('reason')->nullable();
            $table->timestamp('locked_at')->useCurrent();
            $table->timestamp('unlocked_at')->nullable();
            $table->uuid('unlocked_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('unlocked_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['lockable_type', 'lockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_lockouts');
    }
};
