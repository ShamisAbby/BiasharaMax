<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic registry for every third-party connection NOT already
     * covered by a dedicated table: payment gateways live in
     * `payment_gateways` (Finance), messaging channels in
     * `notification_channels` (Operations), outbound webhooks in
     * `webhooks` (Developer Center) — none of those are duplicated
     * here. This table covers OAuth providers, maps/analytics, AI
     * providers, chat/automation (Slack/Discord/Zapier/Make), and cloud
     * storage (Drive/Dropbox/OneDrive/S3/R2). Same "real but unconfigured
     * until real keys are added" pattern as every other provider table
     * in this codebase.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 30);
            $table->string('provider', 40);
            $table->boolean('is_enabled')->default(false);
            $table->string('mode', 10)->default('sandbox');
            $table->text('credentials')->nullable();
            $table->string('webhook_url')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result', 20)->nullable();
            $table->string('documentation_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['category', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
