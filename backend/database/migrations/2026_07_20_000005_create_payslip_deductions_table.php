<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->string('deduction_type'); // income_tax/nhif/nssf/pension/loan_repayment/other
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_deductions');
    }
};
