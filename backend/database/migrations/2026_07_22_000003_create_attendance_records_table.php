<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->uuid('employee_profile_id');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->cascadeOnDelete();
            $table->uuid('shift_id')->nullable();
            $table->foreign('shift_id')->references('id')->on('attendance_shifts')->nullOnDelete();
            $table->uuid('leave_request_id')->nullable(); // populated when status = on_leave

            $table->date('attendance_date');
            $table->string('day_type')->default('regular'); // regular|holiday|weekend
            $table->string('status')->default('absent'); // present|absent|late|half_day|on_leave|holiday

            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->timestamp('break_start_at')->nullable();
            $table->timestamp('break_end_at')->nullable();

            $table->decimal('regular_hours', 8, 2)->nullable();
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('break_hours', 8, 2)->default(0);

            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->boolean('early_departure')->default(false);

            $table->string('clock_in_method')->default('manual'); // manual|qr|pin|biometric|gps
            $table->string('location_latitude')->nullable();
            $table->string('location_longitude')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->uuid('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->unique(['employee_profile_id', 'attendance_date']);
            $table->index(['business_id', 'attendance_date']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
