<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable starter permission sets a SuperAdmin can apply when
     * creating a role — distinct from the auto-provisioned default
     * roles (RoleProvisioningService). `scope` mirrors permissions.scope:
     * a template targets either tenant roles or platform roles.
     */
    public function up(): void
    {
        Schema::create('role_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('scope', 20)->default('tenant');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_templates');
    }
};
