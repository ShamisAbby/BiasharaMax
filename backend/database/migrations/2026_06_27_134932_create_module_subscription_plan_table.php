<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Assign to Plans" — which subscription plans include which
     * modules. Combined with business_type_module and business_module,
     * a business's effective module set is: plan modules ∩ business
     * type defaults, overridable per-business via business_module.
     */
    public function up(): void
    {
        Schema::create('module_subscription_plan', function (Blueprint $table) {
            $table->uuid('module_id');
            $table->uuid('subscription_plan_id');
            $table->timestamps();

            $table->primary(['module_id', 'subscription_plan_id']);
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_subscription_plan');
    }
};
