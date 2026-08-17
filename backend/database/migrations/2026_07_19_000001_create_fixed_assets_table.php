<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('asset_code');
            $table->string('asset_name');
            $table->string('category'); // land/building/vehicle/equipment/furniture/intangible/other
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 14, 2);
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('accumulated_depreciation_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('depreciation_expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('useful_life_months');
            $table->decimal('residual_value', 14, 2)->default(0);
            $table->string('depreciation_method')->default('straight_line'); // straight_line/declining_balance/none
            $table->string('status')->default('active'); // active/fully_depreciated/disposed
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 14, 2)->nullable();
            $table->foreignUuid('disposal_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'asset_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
