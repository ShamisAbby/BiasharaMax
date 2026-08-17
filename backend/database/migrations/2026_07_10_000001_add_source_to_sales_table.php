<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes POS/in-store sales from the new Online Store checkout
     * flow — both funnel through the same Sale model and SaleService so
     * inventory, accounting and CRM integrations are never duplicated.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('source', 20)->default('pos')->after('warehouse_id');
            $table->text('delivery_address')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['source', 'delivery_address']);
        });
    }
};
