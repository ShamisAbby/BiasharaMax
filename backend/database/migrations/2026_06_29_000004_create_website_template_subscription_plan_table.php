<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_template_subscription_plan', function (Blueprint $table) {
            $table->uuid('website_template_id');
            $table->uuid('subscription_plan_id');

            $table->foreign('website_template_id')->references('id')->on('website_templates')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->primary(['website_template_id', 'subscription_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_template_subscription_plan');
    }
};
