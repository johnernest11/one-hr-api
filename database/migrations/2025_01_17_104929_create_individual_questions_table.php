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
        Schema::create('individual_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();

            /* ------------------------------- Question 34 ------------------------------ */
            $table->boolean('q34_a')->nullable();
            $table->boolean('q34_b')->nullable();
            $table->string('q34_details')->nullable();

            /* ------------------------------- Question 35 ------------------------------ */
            $table->boolean('q35_a')->nullable();
            $table->string('q35_a_details')->nullable();
            $table->boolean('q35_b')->nullable();
            $table->date('q35_b_date_filed')->nullable();
            $table->string('q35_b_status')->nullable();

            /* ------------------------------- Question 36 ------------------------------ */
            $table->boolean('q36')->nullable();
            $table->string('q36_details')->nullable();

            /* ------------------------------- Question 37 ------------------------------ */
            $table->boolean('q37')->nullable();
            $table->string('q37_details')->nullable();

            /* ------------------------------- Question 38 ------------------------------ */
            $table->boolean('q38_a')->nullable();
            $table->string('q38_a_details')->nullable();
            $table->boolean('q38_b')->nullable();
            $table->string('q38_b_details')->nullable();

            /* ------------------------------- Question 39 ------------------------------ */
            $table->boolean('q39')->nullable();

            /* ------------------------------- Question 40 ------------------------------ */
            $table->boolean('q40_a_indigenous_group')->nullable();
            $table->string('q40_a_details')->nullable();
            $table->boolean('q40_b_pwd')->nullable();
            $table->string('q40_b_details')->nullable();
            $table->boolean('q40_c_solo_parent')->nullable();
            $table->string('q40_c_details')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_questions');
    }
};
