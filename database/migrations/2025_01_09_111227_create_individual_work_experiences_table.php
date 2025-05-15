<?php

use App\Enums\EmploymentStatus;
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
        Schema::create('individual_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();
            $table->boolean('is_current_work')->default(false);
            $table->date('inclusive_date_from')->nullable();
            $table->date('inclusive_date_to')->nullable();
            $table->string('position_title')->nullable();
            $table->string('department_agency_office_company')->nullable();
            $table->double('monthly_salary')->nullable();
            $table->foreignId('salary_grade_id')->nullable()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('status_of_appointment', ConversionHelper::enumToArray(EmploymentStatus::class))->nullable();
            $table->boolean('is_gov_service')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_work_experiences');
    }
};
