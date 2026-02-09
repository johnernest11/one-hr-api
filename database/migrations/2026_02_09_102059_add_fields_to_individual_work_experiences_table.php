<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('individual_work_experiences', function (Blueprint $table) {
            $table->string('immediate_supervisor')->nullable()->after('is_gov_service');
            $table->string('office_unit')->nullable()->after('immediate_supervisor'); // Mapping to name_of_office_unit
            $table->text('significant_accomplishments')->nullable()->after('office_unit'); // Mapping to list_of_accomplishments
            $table->text('summary_of_actual_duties')->nullable()->after('significant_accomplishments'); // Mapping to summary_of_duties
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('individual_work_experiences', function (Blueprint $table) {
            $table->dropColumn([
                'immediate_supervisor',
                'office_unit',
                'significant_accomplishments',
                'summary_of_actual_duties',
            ]);
        });
    }
};
