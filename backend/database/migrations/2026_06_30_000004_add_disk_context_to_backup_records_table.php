<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only — extends the existing `backup_records` table (built
     * in the Platform Operations sprint) rather than creating a parallel
     * one for the real spatie/laravel-backup integration.
     */
    public function up(): void
    {
        Schema::table('backup_records', function (Blueprint $table) {
            $table->string('disk', 30)->default('local')->after('type');
            $table->boolean('is_encrypted')->default(false)->after('disk');
            $table->string('triggered_by', 20)->default('manual')->after('is_encrypted');
            $table->text('notes')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('backup_records', function (Blueprint $table) {
            $table->dropColumn(['disk', 'is_encrypted', 'triggered_by', 'notes']);
        });
    }
};
