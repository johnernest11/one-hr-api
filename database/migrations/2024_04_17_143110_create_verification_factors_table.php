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
        Schema::create('verification_factors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type'); // sms, email, google-authenticator
            $table->text('secret');

            /**
             * App-based MFA only shows the QR code once for the user to scan,
             * we use this flag to check weather to show generate the QR code or not.
             * Deliver-based MFA (Email, SMS) typically do not have this feature and
             * have the `enrolled_at` value filled in by default
             */
            $table->timestamp('enrolled_at')->nullable();

            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_factors');
    }
};
