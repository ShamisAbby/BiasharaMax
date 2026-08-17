<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-referential — "this module requires that module to be
     * enabled first." Dependency-cycle/self-reference validation is
     * application-level (Phase 2), not a DB constraint.
     */
    public function up(): void
    {
        Schema::create('module_dependencies', function (Blueprint $table) {
            $table->uuid('module_id');
            $table->uuid('depends_on_module_id');
            $table->timestamps();

            $table->primary(['module_id', 'depends_on_module_id']);
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('depends_on_module_id')->references('id')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_dependencies');
    }
};
