<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();

            $table->string('name'); // Annual Leave, Sick Leave, etc.
            $table->string('code'); // ANNUAL, SICK, EMERGENCY, MATERNITY, PATERNITY, COMPASSIONATE, STUDY, UNPAID
            $table->string('color')->default('#4F46E5');
            $table->unsignedInteger('days_per_year')->default(21);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('can_carry_forward')->default(false);
            $table->unsignedInteger('max_carry_forward_days')->default(0);
            $table->unsignedInteger('min_notice_days')->default(0);
            $table->boolean('gender_restricted')->default(false);
            $table->string('gender_restriction')->nullable(); // male|female (for paternity/maternity)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // protected system types

            $table->timestamps();

            $table->unique(['business_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
