<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('customer_group_id')->nullable()->after('customer_type');
            $table->integer('loyalty_points')->default(0)->after('current_balance');

            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
            $table->index('customer_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['customer_group_id']);
            $table->dropColumn(['customer_group_id', 'loyalty_points']);
        });
    }
};
