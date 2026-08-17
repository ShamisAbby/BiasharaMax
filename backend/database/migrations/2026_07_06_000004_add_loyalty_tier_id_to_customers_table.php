<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('loyalty_tier_id')->nullable()->after('customer_group_id');
            $table->foreign('loyalty_tier_id')->references('id')->on('loyalty_tiers')->nullOnDelete();
            $table->index('loyalty_tier_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['loyalty_tier_id']);
            $table->dropColumn('loyalty_tier_id');
        });
    }
};
