<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_user_id')->constrained('platform_users');
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('business_id')->constrained('businesses');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['platform_user_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
    }
};
