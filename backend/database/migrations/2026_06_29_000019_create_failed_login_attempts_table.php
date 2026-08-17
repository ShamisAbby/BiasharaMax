<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Existing login throttling (RateLimiter, cache-backed)
     * stays as-is for the actual rate-limit decision — this table is the
     * persisted, queryable history Security Center needs to show, which
     * a cache-only limiter can't provide.
     */
    public function up(): void
    {
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('guard', 20);
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('reason', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'guard']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
    }
};
