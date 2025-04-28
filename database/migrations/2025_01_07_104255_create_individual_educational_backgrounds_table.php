<?php

use App\Enums\AcademicLevel;
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
        Schema::create('individual_educational_backgrounds', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('individual_basic_detail_id');
            $table->foreign('individual_basic_detail_id', 'indvl_edu_bg_indvl_basic_detail_id_foreign')
                ->references('id')
                ->on('individual_basic_details')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->string('schools_name')->nullable();
            $table->string('education_description')->nullable();
            $table->enum('level', ConversionHelper::enumToArray(AcademicLevel::class));
            $table->year('period_of_attendance_from')->nullable();
            $table->year('period_of_attendance_to')->nullable();
            $table->string('highest_level_units_earned')->nullable();
            $table->year('year_graduated')->nullable();
            $table->string('scholarship_academic_honors_received')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_educational_backgrounds');
    }
};
