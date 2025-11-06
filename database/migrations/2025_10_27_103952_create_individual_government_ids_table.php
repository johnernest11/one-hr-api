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
        Schema::create('individual_government_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('gov_issued_id')->nullable();
            $table->string('gov_id_no')->nullable();
            $table->string('gov_issuance')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_government_ids');
    }
};
