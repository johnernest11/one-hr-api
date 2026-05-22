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
        Schema::table('items', function (Blueprint $table) {
            // Organization Data (Foreign Keys)
            // Added after 'id' to keep the schema logically organized
            $table->foreignId('division_id')->nullable()->after('id')->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('section_or_unit_id')->nullable()->after('division_id')->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('program_id')->nullable()->after('section_or_unit_id')->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('office_id')->nullable()->after('program_id')->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('psipop_id')->nullable()->after('office_id')->constrained('divisions')->nullOnDelete()->cascadeOnUpdate();

            // Compensation & Employment Details
            $table->foreignId('salary_grade_id')->nullable()->after('employment_status')->constrained()->nullOnDelete()->cascadeOnUpdate();
            // Position Details
            $table->string('item_classification')->nullable()->after('position_id');

            // Designation and Assignment Details
            $table->string('designation')->nullable()->after('date_of_creation');
            $table->date('date_of_designation')->nullable()->after('designation');
            $table->string('special_order_number')->nullable()->after('date_of_designation');

            // Position History and Vacancy Tracking
            $table->string('mode_of_accession')->nullable()->after('status');
            $table->text('history_of_position')->nullable()->after('date_filled_up');
            $table->string('former_incumbent')->nullable()->after('history_of_position');
            $table->string('mode_of_separation')->nullable()->after('former_incumbent');
            $table->date('date_of_vacant')->nullable()->after('mode_of_separation');
            $table->text('remarks_of_vacancy')->nullable()->after('date_of_vacant');
            $table->string('status_of_vacant_position')->nullable()->after('remarks_of_vacancy');
            $table->string('direct_contact_exposure_with_client')->nullable()->after('status_of_vacant_position');
            $table->text('remarks')->nullable()->after('direct_contact_exposure_with_client');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Drop Foreign Key Constraints First
            $table->dropForeign(['division_id']);
            $table->dropForeign(['section_or_unit_id']);
            $table->dropForeign(['program_id']);
            $table->dropForeign(['office_id']);
            $table->dropForeign(['psipop_id']);
            $table->dropForeign(['salary_grade_id']);

            // Drop Columns
            $table->dropColumn([
                'division_id',
                'section_or_unit_id',
                'program_id',
                'office_id',
                'psipop_id',
                'salary_grade_id',
                'item_classification',
                'designation',
                'date_of_designation',
                'special_order_number',
                'mode_of_accession',
                'history_of_position',
                'former_incumbent',
                'mode_of_separation',
                'date_of_vacant',
                'remarks_of_vacancy',
                'status_of_vacant_position',
                'direct_contact_exposure_with_client',
                'remarks',
            ]);
        });
    }
};
