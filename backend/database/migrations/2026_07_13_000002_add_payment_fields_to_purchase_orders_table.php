<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('paid_amount', 14, 2)->default(0)->after('total_amount');
            $table->decimal('balance_due', 14, 2)->default(0)->after('paid_amount');
            $table->string('payment_status', 20)->default('unpaid')->after('balance_due');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'balance_due', 'payment_status']);
        });
    }
};
