<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_template_id')->nullable();
            $table->string('name');
            $table->string('channel', 20);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('audience_type', 30);
            $table->json('audience_filter')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('notification_template_id')->references('id')->on('notification_templates')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
