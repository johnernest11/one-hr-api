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
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('individual_basic_detail_id')->nullable()->constrained('individual_basic_details')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // 1. Drop the foreign key constraint FIRST
            $table->dropForeign(['individual_basic_detail_id']);
            // 2. Then, drop the column
            $table->dropColumn('individual_basic_detail_id');
        });
    }
};
