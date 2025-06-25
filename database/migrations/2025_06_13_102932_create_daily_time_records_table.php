<?php

use App\Enums\DocumentStatus;
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
        Schema::create('daily_time_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('date');
            $table->integer('ut')->default(0);
            $table->boolean('is_edit_ut')->default(false);
            $table->integer('ot')->default(0);
            $table->boolean('is_missing')->default(false);
            $table->text('employee_remarks')->nullable();
            $table->text('hr_remarks')->nullable();
            $table->enum('status', ConversionHelper::enumToArray(DocumentStatus::class))->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_time_records');
    }
};
