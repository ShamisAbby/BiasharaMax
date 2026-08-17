<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('expense_category_id')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->uuid('employee_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->string('payment_method', 30)->default('cash');
            $table->string('status', 20)->default('pending');

            $table->string('receipt_path')->nullable();

            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency', 20)->nullable();
            $table->date('next_recurrence_date')->nullable();

            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('employee_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['business_id', 'expense_date']);
            $table->index(['business_id', 'status']);
            $table->index('expense_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
