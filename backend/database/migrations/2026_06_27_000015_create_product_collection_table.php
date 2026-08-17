<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_collection', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('collection_id');
            $table->timestamps();

            $table->primary(['product_id', 'collection_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_collection');
    }
};
