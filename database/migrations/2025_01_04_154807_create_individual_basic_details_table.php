<?php

use App\Enums\BloodType;
use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\ExtensionNameCategory;
use App\Enums\SexualCategory;
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
        Schema::create('individual_basic_details', function (Blueprint $table) {
            $table->id();

            $table->string('first_name')->fulltext();
            $table->string('last_name')->fulltext();
            $table->string('middle_name')->nullable()->fulltext();
            $table->enum('ext_name', ConversionHelper::enumToArray(ExtensionNameCategory::class))->nullable();
            $table->date('birthday');
            $table->enum('sex', ConversionHelper::enumToArray(SexualCategory::class));

            $table->fullText(['first_name', 'last_name', 'middle_name'], 'individual_full_name_fulltext');
            $table->unique(['first_name', 'last_name', 'middle_name', 'ext_name', 'birthday'], 'individual_name_birthday_unique'); // Renamed column since it is too long

            $table->string('place_of_birth');
            $table->enum('civil_status', ConversionHelper::enumToArray(CivilStatus::class));
            $table->double('height');
            $table->double('weight');
            $table->enum('blood_type', ConversionHelper::enumToArray(BloodType::class));
            $table->string('gsis_no')->nullable();
            $table->string('pag_ibig_no');
            $table->string('philhealth_no');
            $table->string('sss_no');
            $table->string('tin');
            $table->enum('citizenship', ConversionHelper::enumToArray(Citizenship::class));
            $table->enum('citizenship_acquisition', ConversionHelper::enumToArray(CitizenshipAcquisition::class))->nullable();
            // @todo Add field later once libraries for list of countries are added
            // $table->string('citizenship_country')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_basic_details');
    }
};
