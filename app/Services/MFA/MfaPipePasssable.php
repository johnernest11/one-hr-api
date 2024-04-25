<?php

namespace App\Services\MFA;

use App\Enums\MfaOption;
use App\Enums\MfaPipelineAction;
use App\Models\User;

class MfaPipePasssable
{
    public MfaPipelineAction $action;

    public User $user;

    public MfaOption $currentStep;

    public function __construct(MfaPipelineAction $action, User $user, MfaOption $mfaOption)
    {
        $this->action = $action;
        $this->user = $user;
        $this->currentStep = $mfaOption;
    }
}
