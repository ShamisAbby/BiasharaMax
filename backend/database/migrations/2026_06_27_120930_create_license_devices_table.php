<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('license_id');
            $table->string('hardware_fingerprint');
            $table->string('machine_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->foreign('license_id')->references('id')->on('licenses')->cascadeOnDelete();
            $table->unique(['license_id', 'hardware_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_devices');
    }
};
