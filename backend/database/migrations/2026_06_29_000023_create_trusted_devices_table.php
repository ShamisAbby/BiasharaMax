<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('authenticatable_type', 20);
            $table->uuid('authenticatable_id');
            $table->string('device_fingerprint');
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('trusted_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['authenticatable_type', 'authenticatable_id', 'device_fingerprint'], 'trusted_devices_authenticatable_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
