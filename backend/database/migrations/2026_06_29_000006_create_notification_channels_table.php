<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors the `payment_gateways` pattern from the Finance module: a
     * real, selectable channel that starts disabled with no credentials.
     * A driver refuses to send through it until real provider keys are
     * configured — never a fabricated "sent" result.
     */
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('channel', 20);
            $table->string('provider', 40);
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->text('credentials')->nullable();
            $table->string('sender_id')->nullable();
            $table->string('webhook_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['channel', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
    }
};
