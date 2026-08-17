<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('employee_number');
            $table->date('employment_date');
            $table->string('employment_type')->default('full_time'); // full_time/part_time/contract
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->decimal('base_salary', 14, 2);
            $table->string('salary_cycle')->default('monthly'); // monthly/bi_weekly/weekly
            $table->string('tax_identification_number')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('status')->default('active'); // active/on_leave/terminated
            $table->date('termination_date')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'user_id']);
            $table->unique(['business_id', 'employee_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
