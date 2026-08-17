<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors permission_role's shape exactly. Reuses the existing
     * global `permissions` table (it already has no business_id) rather
     * than duplicating it — see the new `scope` column added to
     * `permissions` in a later migration in this batch.
     */
    public function up(): void
    {
        Schema::create('platform_permission_role', function (Blueprint $table) {
            $table->uuid('platform_role_id');
            $table->uuid('permission_id');
            $table->timestamps();

            $table->primary(['platform_role_id', 'permission_id']);
            $table->foreign('platform_role_id')->references('id')->on('platform_roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_permission_role');
    }
};
