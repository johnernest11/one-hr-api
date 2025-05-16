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
        Schema::create('individual_voluntary_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();
            $table->boolean('is_current_org')->default(false);
            $table->string('org_name')->nullable();
            $table->string('org_address')->nullable();
            $table->date('from')->nullable();
            $table->date('to')->nullable();
            $table->double('number_of_hours')->nullable();
            $table->string('position_nature_of_work')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_voluntary_works');
    }
};
