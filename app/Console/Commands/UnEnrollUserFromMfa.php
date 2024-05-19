<?php

namespace App\Console\Commands;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Services\MfaOrchestrator;
use App\Services\Verification\AppVerificationMethod;
use App\Services\Verification\DeliveryVerificationMethod;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UnEnrollUserFromMfa extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mfa:un-enroll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Un-enroll user from an MFA method';

    private MfaOrchestrator $mfaOrchestrator;

    public function __construct(MfaOrchestrator $mfaOrchestrator)
    {
        parent::__construct();
        $this->mfaOrchestrator = $mfaOrchestrator;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = Str::lower($this->ask('Enter the email address of the user you want to un-enroll'));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("The email address: $email does not belong to any user");

            return Command::FAILURE;
        }

        $registeredMfaClasses = config('auth.mfa_methods');
        $mfaVerificationMethods = [];
        foreach ($registeredMfaClasses as $class) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $verificationMethod */
            $verificationMethod = resolve($class);
            $name = $verificationMethod->verificationMethod()->value;
            $mfaVerificationMethods[$name] = Str::title(Str::replace('_', ' ', $name));
        }

        $chosenMethod = $this->choice('Which MFA method should be un-enrolled?', $mfaVerificationMethods);
        $success = $this->mfaOrchestrator->unEnrollUser($user, VerificationMethod::from($chosenMethod));

        if (! $success) {
            $this->error('Unable to un-enroll user');

            return Command::FAILURE;
        }

        $fullName = $user->userProfile->full_name;
        $this->info("$fullName has been successfully un-enrolled from $chosenMethod");

        return Command::SUCCESS;
    }
}
