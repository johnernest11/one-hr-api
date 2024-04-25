<?php

namespace App\Traits\Services;

use App\Enums\MfaPipelineAction;
use App\Services\MFA\MfaPipePasssable;

trait MfaPipeStage
{
    public function handle(MfaPipePasssable $data, callable $next): void
    {
        // Handle Secret generation
        if ($data->action === MfaPipelineAction::GENERATE_SECRET) {
            $this->generateSecret($data->user);
            $next($data);

            return;
        }

        // Handle Code delivery for Channel-based verifications
        if ($data->action === MfaPipelineAction::SEND_CODE) {
            $next($data);

            return;
        }
    }
}
