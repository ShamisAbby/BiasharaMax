<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->uuid('employee_profile_id');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->cascadeOnDelete();
            $table->uuid('leave_type_id');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 8, 2);
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_period')->nullable(); // morning|afternoon

            $table->string('status')->default('pending'); // pending|approved|rejected|cancelled
            $table->text('reason');
            $table->string('attachment_path')->nullable();

            $table->uuid('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->boolean('payroll_adjusted')->default(false);

            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['employee_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
