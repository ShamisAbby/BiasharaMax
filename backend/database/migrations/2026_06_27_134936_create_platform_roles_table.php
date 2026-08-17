<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-level RBAC is genuinely new — PlatformUser has had no
     * role/permission concept until now (every SuperAdmin account had
     * identical, unrestricted access). Kept as a separate table rather
     * than reusing the tenant-scoped `roles` table (which has a
     * NOT NULL business_id with cascadeOnDelete), per the decision not
     * to alter that table's existing shape.
     */
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_roles');
    }
};
