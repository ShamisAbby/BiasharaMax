<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10);
            $table->decimal('exchange_rate_to_base', 14, 6)->default(1);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('rate_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
