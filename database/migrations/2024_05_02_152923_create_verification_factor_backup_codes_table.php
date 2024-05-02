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
        Schema::create('verification_factor_backup_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_factor_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_factor_backup_codes');
    }
};
