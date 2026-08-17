<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_tax_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('tax_rate_id')->constrained('tax_rates')->restrictOnDelete();
            $table->string('tax_type'); // vat/gst/sales_tax/income_tax/withholding
            $table->string('applies_to'); // sales/purchases/both
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Explicit short name — same MySQL 64-char identifier limit
            // issue as two_factor_credentials (see that migration's
            // comment); Laravel's auto-generated name here is 67 chars.
            $table->unique(['business_id', 'tax_rate_id', 'tax_type'], 'business_tax_configs_business_tax_rate_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_tax_configurations');
    }
};
