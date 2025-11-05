<?php

use App\Enums\ApprovalType;
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
        Schema::create('locator_slip_loggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locator_slip_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('date');
            $table->time('time_out')->nullable();
            $table->time('time_in')->nullable();
            $table->text('destination');
            $table->text('purpose');
            $table->enum('approved_for', ConversionHelper::enumToArray(ApprovalType::class));
            $table->boolean('is_wellness')->default(false);
            $table->double('duration');
            $table->longText('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locator_slip_loggers');
    }
};
