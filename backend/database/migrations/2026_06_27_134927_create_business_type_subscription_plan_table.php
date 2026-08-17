<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which subscription plans are eligible for which business types
     * ("Subscription Eligibility").
     */
    public function up(): void
    {
        Schema::create('business_type_subscription_plan', function (Blueprint $table) {
            $table->uuid('business_type_id');
            $table->uuid('subscription_plan_id');
            $table->timestamps();

            $table->primary(['business_type_id', 'subscription_plan_id']);
            $table->foreign('business_type_id')->references('id')->on('business_types')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_type_subscription_plan');
    }
};
