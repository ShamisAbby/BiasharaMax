<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('authenticatable_type', 20);
            $table->uuid('authenticatable_id');
            $table->text('secret');
            $table->text('recovery_codes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();

            // Explicit short name: Laravel's auto-generated name for this
            // column pair ('two_factor_credentials_authenticatable_type_
            // authenticatable_id_unique', 69 chars) exceeds MySQL's 64-char
            // identifier limit and throws a hard error (1059). Postgres has
            // the same 63-byte limit but silently truncates instead of
            // erroring, which is why this went unnoticed until the first
            // real MySQL migration run.
            $table->unique(['authenticatable_type', 'authenticatable_id'], 'two_factor_credentials_authenticatable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_credentials');
    }
};
