<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ip_address', 45)->unique();
            $table->text('reason')->nullable();
            $table->uuid('blocked_by')->nullable();
            $table->boolean('is_permanent')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('blocked_by')->references('id')->on('platform_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};
