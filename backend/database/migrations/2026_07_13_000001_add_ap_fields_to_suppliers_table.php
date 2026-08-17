<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('credit_limit', 14, 2)->nullable()->after('status');
            $table->decimal('current_balance', 14, 2)->default(0)->after('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'current_balance']);
        });
    }
};
