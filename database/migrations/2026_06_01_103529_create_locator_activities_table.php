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
        Schema::create('locator_activities', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('data')->nullable();
            $table->boolean('selectable')->default(true);
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('locator_activities')
                ->onDelete('cascade');

            $table->timestamps();

            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locator_activities');
    }
};
