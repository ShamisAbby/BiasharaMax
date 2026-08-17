<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets offline clients (Flutter Desktop) generate a key client-side at
     * the moment a sale is completed, before there's any network path to
     * the server. A retried sync push after a dropped connection is then
     * safe to replay — SaleService::create() treats a repeat key as "this
     * already happened" and returns the original sale instead of creating
     * a duplicate. Nullable and only unique per-business (not globally)
     * since two different businesses' offline clients could coincidentally
     * generate the same UUID with vanishingly small but nonzero odds — the
     * per-business scope costs nothing and removes even that.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('source');
            $table->unique(['business_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
