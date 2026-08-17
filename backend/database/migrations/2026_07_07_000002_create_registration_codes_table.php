<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->foreignUuid('plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->string('billing_cycle', 20)->default('yearly');
            $table->unsignedSmallInteger('duration_months')->default(12);
            $table->string('description')->nullable();
            $table->string('status', 20)->default('available'); // available | used | expired
            $table->date('expires_at')->nullable();
            $table->foreignUuid('used_by')->nullable()->references('id')->on('businesses')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->references('id')->on('platform_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_codes');
    }
};
