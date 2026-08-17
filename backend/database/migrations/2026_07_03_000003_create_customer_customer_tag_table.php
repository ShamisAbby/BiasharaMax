<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_customer_tag', function (Blueprint $table) {
            $table->uuid('customer_id');
            $table->uuid('customer_tag_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('customer_tag_id')->references('id')->on('customer_tags')->cascadeOnDelete();

            $table->primary(['customer_id', 'customer_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_customer_tag');
    }
};
