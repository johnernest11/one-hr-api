<?php

namespace App\Traits\Services;

use App\Enums\MfaPipelineAction;
use App\Services\MFA\MfaPipePasssable;

trait MfaPipeStage
{
    /**
     * Handle the different Pipeline Stages
     *
     * Secret Generation - Generate and a secret key for the user. Return the secret<string> generated
     * Send Code - Generate an OTP and send to the user. Return the code<string> sent to the user. (Delivery-based channels only)
     * Verify Code - Verify the code and return if the code is valid<bool>
     * Generate QR - Generate a QR code and return the base64 format string
     */
    public function handle(MfaPipePasssable $data, callable $next): null|string|bool
    {
        // Handle Secret generation
        if ($data->action === MfaPipelineAction::GENERATE_SECRET) {

            if ($data->currentStep === $this->verificationMethod()) {
                return $this->generateSecret($data->user);
            }

            $next($data);
        }

        // Handle Code delivery for Channel-based verifications
        if ($data->action === MfaPipelineAction::SEND_CODE) {

            if ($data->currentStep === $this->verificationMethod()) {
                $code = $this->generateCode($data->user);

                return $this->sendCode($data->user, $code);
            }

            $next($data);
        }

        return null;
    }
}
