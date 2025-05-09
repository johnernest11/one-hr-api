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
        Schema::create('individual_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('eligibility')->nullable();
            $table->double('rating')->nullable();
            $table->date('date_of_examination_conferment')->nullable();
            $table->string('place_of_examination')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_date_of_validity')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_eligibilities');
    }
};
