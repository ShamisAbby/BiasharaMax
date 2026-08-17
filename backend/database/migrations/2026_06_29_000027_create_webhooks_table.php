<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic outbound webhook subscriptions — distinct from the
     * Finance module's `payment_gateway_logs` (which logs calls to/from
     * payment providers specifically). `business_id` is nullable:
     * platform-level webhooks (e.g. "notify us when any business is
     * created") have none; a future tenant-facing webhooks feature
     * would set it.
     */
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id')->nullable();
            $table->string('name');
            $table->string('url');
            $table->json('events');
            $table->text('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
