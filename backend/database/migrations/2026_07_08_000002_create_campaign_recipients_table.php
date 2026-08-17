<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketing_campaign_id');
            $table->uuid('customer_id')->nullable();
            $table->string('email');
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('marketing_campaign_id')->references('id')->on('marketing_campaigns')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index(['marketing_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
