<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->uuid('currency_id')->nullable()->after('description');
            $table->decimal('exchange_rate', 14, 6)->default(1)->after('currency_id');
            $table->decimal('foreign_amount', 14, 2)->nullable()->after('exchange_rate');

            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['currency_id', 'exchange_rate', 'foreign_amount']);
        });
    }
};
