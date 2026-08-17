<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('business_id')->nullable()->after('id');
            $table->uuid('role_id')->nullable()->after('business_id');
            $table->uuid('invited_by')->nullable()->after('role_id');
            $table->uuid('created_by')->nullable()->after('invited_by');
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn(['business_id', 'role_id', 'invited_by', 'created_by', 'updated_by', 'deleted_by']);
        });
    }
};
