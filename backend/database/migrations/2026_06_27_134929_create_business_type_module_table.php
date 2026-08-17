<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Default Modules" a business type ships with — read at
     * business-registration time to seed that business's own
     * business_module rows.
     */
    public function up(): void
    {
        Schema::create('business_type_module', function (Blueprint $table) {
            $table->uuid('business_type_id');
            $table->uuid('module_id');
            $table->timestamps();

            $table->primary(['business_type_id', 'module_id']);
            $table->foreign('business_type_id')->references('id')->on('business_types')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_type_module');
    }
};
