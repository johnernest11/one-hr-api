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
        Schema::table('daily_time_records', function (Blueprint $table) {
            $table->decimal('ut', 5, 2)->default(0)->change();
            $table->decimal('ot', 5, 2)->default(0)->change();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_time_records', function (Blueprint $table) {
            $table->integer('ut')->default(0)->change();
            $table->integer('ot')->default(0)->change();
        });
    }
};
