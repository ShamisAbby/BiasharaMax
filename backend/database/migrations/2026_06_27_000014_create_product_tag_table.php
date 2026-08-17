<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tag', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('tag_id');
            $table->timestamps();

            $table->primary(['product_id', 'tag_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tag');
    }
};
