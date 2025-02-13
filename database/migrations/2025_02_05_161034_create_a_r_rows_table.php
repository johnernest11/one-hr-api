<?php

use App\Enums\WeekNumber;
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
        Schema::create('a_r_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accomplishment_report_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('week_num', ConversionHelper::enumToArray(WeekNumber::class))->index();
            $table->string('dates_in_week')->nullable();
            $table->longText('specific_activity')->nullable();
            $table->longText('highlights')->nullable();

            $table->fullText(['highlights']);
            $table->fullText(['specific_activity']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_r_rows');
    }
};
