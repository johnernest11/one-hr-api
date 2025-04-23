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
        Schema::create('individual_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->unique()->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('residential_house_block_lot_no')->nullable();
            $table->text('residential_street')->nullable();
            $table->text('residential_subdivision_village')->nullable();
            $table->foreignId('residential_brgy_id')->constrained('barangays')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('residential_citymun_id')->constrained('cities')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('residential_province_id')->constrained('provinces')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('residential_region_id')->constrained('regions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('residential_zip_code');

            $table->text('permanent_house_block_lot_no')->nullable();
            $table->text('permanent_street')->nullable();
            $table->text('permanent_subdivision_village')->nullable();
            $table->foreignId('permanent_brgy_id')->constrained('barangays')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('permanent_citymun_id')->constrained('cities')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('permanent_province_id')->constrained('provinces')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('permanent_region_id')->constrained('regions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('permanent_zip_code');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_addresses');
    }
};
