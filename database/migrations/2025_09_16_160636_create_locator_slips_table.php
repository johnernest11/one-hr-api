<?php

use App\Enums\DocumentStatus;
use App\Enums\LocatorFormType;
use App\Enums\Period;
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
        Schema::create('locator_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('locator_slip_no')->nullable();
            $table->date('date');
            $table->enum('period', ConversionHelper::enumToArray(Period::class))->nullable();
            $table->enum('status', ConversionHelper::enumToArray(DocumentStatus::class))->index()->nullable();
            $table->enum('form_type', ConversionHelper::enumToArray(LocatorFormType::class))->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locator_slips');
    }
};
