<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->uuid('attendance_record_id');
            $table->foreign('attendance_record_id')->references('id')->on('attendance_records')->cascadeOnDelete();
            $table->uuid('employee_profile_id');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->cascadeOnDelete();

            $table->timestamp('requested_clock_in')->nullable();
            $table->timestamp('requested_clock_out')->nullable();
            $table->text('reason');

            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->uuid('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
