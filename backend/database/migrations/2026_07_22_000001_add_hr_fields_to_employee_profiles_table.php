<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('position');
            $table->string('gender')->nullable()->after('birth_date'); // male|female|other
            $table->string('contract_type')->nullable()->after('gender'); // permanent|fixed_term|probation
            $table->date('contract_end_date')->nullable()->after('contract_type');
            $table->date('probation_end_date')->nullable()->after('contract_end_date');
            $table->string('emergency_contact_name')->nullable()->after('probation_end_date');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date', 'gender', 'contract_type',
                'contract_end_date', 'probation_end_date',
                'emergency_contact_name', 'emergency_contact_phone',
            ]);
        });
    }
};
