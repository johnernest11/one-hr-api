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
        Schema::create('cities', function (Blueprint $table) {

            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('code')->unique();
            $table->string('name')->index();
            $table->string('full_name');
            $table->string('alt_name')->nullable();
            $table->enum('classification', ['city', 'municipality'])->index();
            $table->boolean('is_capital');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
