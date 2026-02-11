<?php

use App\Enums\ARStatus;
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
        Schema::create('compensatory_reports', function (Blueprint $table) {
            $table->id();
            $table->string('ctdo_period')->nullable();
            $table->longText('ctdo_supervisor_notes')->nullable();
            $table->enum('ctdo_status', ConversionHelper::enumToArray(ARStatus::class))->index();

            $table->foreignId('user_profile_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compensatory_reports');
    }
};
