<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only delivery log — every notification actually attempted
     * (campaign-driven or single system-triggered) gets one row here,
     * independent of the in-app-only `notifications` table.
     */
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_campaign_id')->nullable();
            $table->uuid('notifiable_id');
            $table->string('notifiable_type');
            $table->string('channel', 20);
            $table->string('recipient');
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->uuid('retry_of_delivery_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('notification_campaign_id')->references('id')->on('notification_campaigns')->nullOnDelete();
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['channel', 'status']);
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->foreign('retry_of_delivery_id')->references('id')->on('notification_deliveries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
