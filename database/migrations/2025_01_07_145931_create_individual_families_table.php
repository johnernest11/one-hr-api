<?php

use App\Enums\ExtensionNameCategory;
use App\Enums\FamilyMemberCategory;
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
        Schema::create('individual_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->enum('ext_name', ConversionHelper::enumToArray(ExtensionNameCategory::class))->nullable();
            $table->string('occupation')->nullable();
            $table->string('employers_business_name')->nullable();
            $table->string('business_address')->nullable();
            $table->string('telephone_no')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('class', ConversionHelper::enumToArray(FamilyMemberCategory::class));
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_families');
    }
};
