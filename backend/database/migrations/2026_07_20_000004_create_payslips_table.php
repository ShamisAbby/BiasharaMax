<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignUuid('employee_profile_id')->constrained('employee_profiles')->restrictOnDelete();
            $table->decimal('basic_salary', 14, 2);
            $table->decimal('total_allowances', 14, 2)->default(0);
            $table->decimal('gross_salary', 14, 2);
            $table->decimal('income_tax', 14, 2)->default(0);
            $table->decimal('social_security', 14, 2)->default(0);
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2);
            $table->string('status')->default('draft'); // draft/approved/paid
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
