<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_allowances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->string('allowance_type'); // housing/transport/medical/performance/other
            $table->decimal('amount', 14, 2);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_allowances');
    }
};
