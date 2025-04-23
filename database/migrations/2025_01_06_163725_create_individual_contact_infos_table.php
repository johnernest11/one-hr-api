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
        Schema::create('individual_contact_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_basic_detail_id')->unique()->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('tel_no')->nullable();
            $table->string('mobile_no');
            $table->string('email_address');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_contact_infos');
    }
};
