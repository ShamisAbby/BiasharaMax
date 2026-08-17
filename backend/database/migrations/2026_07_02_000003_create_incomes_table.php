<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Non-sales income only — "Sales Income" is never duplicated here.
     * It already exists as a first-class, real figure derived from the
     * Sales module's own `sales` table (see FinancialReportService),
     * so the Profit & Loss report combines that with rows from this
     * table (service/other/manual income) rather than re-entering POS
     * revenue by hand.
     */
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('customer_id')->nullable();

            $table->string('category', 20)->default('other');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('income_date');
            $table->string('payment_method', 30)->default('cash');
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->index(['business_id', 'income_date']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
