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
        Schema::table('locator_slips', function (Blueprint $table) {
            $table->double('auxiliary_wellness')->default(2); // monthly auxiliary wellness in hours
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locator_slips', function (Blueprint $table) {
            $table->dropColumn('auxiliary_wellness');
        });
    }
};
