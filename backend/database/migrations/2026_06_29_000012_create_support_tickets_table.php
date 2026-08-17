<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->unique();
            $table->uuid('business_id')->nullable();
            $table->string('opened_by_type', 20);
            $table->uuid('opened_by_id');
            $table->uuid('support_department_id')->nullable();
            $table->uuid('assigned_agent_id')->nullable();
            $table->string('category', 30)->default('other');
            $table->string('priority', 10)->default('medium');
            $table->string('status', 20)->default('open');
            $table->string('subject');
            $table->text('description');
            $table->unsignedTinyInteger('satisfaction_rating')->nullable();
            $table->text('satisfaction_comment')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->nullOnDelete();
            $table->foreign('support_department_id')->references('id')->on('support_departments')->nullOnDelete();
            $table->foreign('assigned_agent_id')->references('id')->on('support_agents')->nullOnDelete();
            $table->index(['status', 'priority']);
            $table->index('assigned_agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
