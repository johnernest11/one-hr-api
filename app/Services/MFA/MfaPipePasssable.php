<?php

namespace App\Services\MFA;

use App\Enums\MfaPipelineAction;
use App\Enums\VerificationMethod;
use App\Models\User;

class MfaPipePasssable
{
    public MfaPipelineAction $action;

    public User $user;

    public VerificationMethod $currentStep;

    public function __construct(MfaPipelineAction $action, User $user, VerificationMethod $currentStep)
    {
        $this->action = $action;
        $this->user = $user;
        $this->currentStep = $currentStep;
    }
}
