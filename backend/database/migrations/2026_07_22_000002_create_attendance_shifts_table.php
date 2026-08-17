<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->string('name'); // Morning Shift, Night Shift, etc.
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('grace_minutes')->default(15);
            $table->unsignedInteger('break_minutes')->default(60);
            $table->unsignedInteger('expected_hours')->default(8);
            $table->boolean('is_overnight')->default(false); // end_time < start_time
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_shifts');
    }
};
