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
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->foreignId('office_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('head_user_id')->nullable()->constrained('user_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->constrained('user_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('last_modified_by_user_id')->nullable()->constrained('user_profiles')->cascadeOnUpdate()->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
