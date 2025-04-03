<?php

use App\Enums\EmploymentStatus;
use App\Enums\ItemStatus;
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
        Schema::create('items', function (Blueprint $table) {
            $table->id();

            $table->string('number');
            $table->date('date_of_creation');
            $table->enum('status', ConversionHelper::enumToArray(ItemStatus::class))->index();
            $table->date('date_filled_up')->nullable();
            $table->enum('employment_status', ConversionHelper::enumToArray(EmploymentStatus::class))->index();

            $table->foreignId('position_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            //@todo add foreign id for fund sources once added.

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
