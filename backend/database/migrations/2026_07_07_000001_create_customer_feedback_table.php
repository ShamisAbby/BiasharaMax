<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('branch_id')->nullable();

            $table->string('type', 20)->default('review');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('open');
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_feedback');
    }
};
