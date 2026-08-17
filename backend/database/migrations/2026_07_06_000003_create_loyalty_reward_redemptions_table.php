<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_reward_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('customer_id');
            $table->uuid('loyalty_reward_id');

            $table->integer('points_spent');
            $table->string('status', 20)->default('pending');
            $table->timestamp('redeemed_at')->useCurrent();
            $table->timestamp('fulfilled_at')->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('loyalty_reward_id')->references('id')->on('loyalty_rewards')->cascadeOnDelete();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_reward_redemptions');
    }
};
