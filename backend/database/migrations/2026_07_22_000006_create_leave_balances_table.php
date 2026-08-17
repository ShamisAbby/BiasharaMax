<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->uuid('employee_profile_id');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->cascadeOnDelete();
            $table->uuid('leave_type_id');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();

            $table->unsignedInteger('year');
            $table->decimal('allocated_days', 8, 2)->default(0);
            $table->decimal('used_days', 8, 2)->default(0);
            $table->decimal('pending_days', 8, 2)->default(0);
            $table->decimal('carried_forward_days', 8, 2)->default(0);
            $table->decimal('available_days', 8, 2)->default(0);

            $table->timestamps();

            $table->unique(['employee_profile_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
