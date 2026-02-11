<?php

use App\Enums\WeekDay;
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
        Schema::create('c_t_d_o_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compensatory_report_id')->constrained('compensatory_reports')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('days_of_the_week', ConversionHelper::enumToArray(WeekDay::class))->index();
            $table->string('work_date')->nullable();
            $table->string('time_start')->nullable();
            $table->string('time_end')->nullable();

            $table->longText('accomplishment')->nullable();
            $table->longText('authorized_claim')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_t_d_o_rows');
    }
};
