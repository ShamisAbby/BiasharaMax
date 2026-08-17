<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_role_template', function (Blueprint $table) {
            $table->uuid('role_template_id');
            $table->uuid('permission_id');
            $table->timestamps();

            $table->primary(['role_template_id', 'permission_id']);
            $table->foreign('role_template_id')->references('id')->on('role_templates')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role_template');
    }
};
