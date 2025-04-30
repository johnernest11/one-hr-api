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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->unique()->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('id_number')->nullable()->unique();

            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete()->cascadeOnUpdate();

            $table->foreignId('salary_grade_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            //@todo add program_id once its completed.
            $table->foreignId('section_or_unit_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('agency_employee_no')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
