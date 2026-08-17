<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog of payment gateways the platform can charge through.
     * `credentials` and `webhook_secret` are encrypted casts on the model
     * (Laravel's `encrypted`/`encrypted:array` cast), never stored or
     * displayed in plain text. A gateway with empty `credentials` is a
     * real, registered gateway that simply isn't configured yet — driver
     * code refuses to charge through it until an admin supplies real keys.
     */
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('provider', 40);
            $table->boolean('is_enabled')->default(false);
            $table->string('mode', 10)->default('sandbox');
            $table->text('credentials')->nullable();
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->json('supported_currencies')->nullable();
            $table->json('supported_countries')->nullable();
            $table->decimal('fee_percentage', 5, 2)->default(0);
            $table->decimal('fee_fixed', 12, 2)->default(0);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->string('health_status', 20)->default('unknown');
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('documentation_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['is_enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
