<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('channel', 20);
            $table->string('category', 40);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
