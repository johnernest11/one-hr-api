<?php

namespace App\Console\Commands;

use App\Enums\VerificationMethod;
use App\Services\AppSettingsManager;
use App\Services\Verification\AppVerificationMethod;
use App\Services\Verification\DeliveryVerificationMethod;
use ConversionHelper;
use Illuminate\Console\Command;
use Str;

class SetUpMfa extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mfa:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup MFA configurations';

    private AppSettingsManager $appSettingsManager;

    public function __construct(AppSettingsManager $appSettingsManager)
    {
        parent::__construct();
        $this->appSettingsManager = $appSettingsManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isEnabled = $this->confirm('Turn-on Multi-Factor Authentication?');
        $allowApiManagement = $this->confirm('Allow MFA configurations to be managed via API endpoints?');

        if (! $isEnabled) {
            $this->appSettingsManager->setMfaConfig(false, $allowApiManagement);
            $this->info('You have disabled multi-factor authentication');
            $this->table(['Key', 'Value'], [
                ['enabled', 'No'],
                ['allow_api_management', $allowApiManagement ? 'Yes' : 'No'],
            ]);

            return Command::SUCCESS;
        }

        $this->printAllAvailableMfaMethods();

        // Build the options for selection
        $allMfaOptions = $this->getAllMfaMethods();
        $totalOptions = count($allMfaOptions);

        $selectedMfaSteps = $this->getMfaOrderInput($totalOptions, $allMfaOptions);
        if (count($selectedMfaSteps) === 0) {
            return Command::FAILURE;
        }

        $this->printSelectedMfaOrder($selectedMfaSteps);
        $confirmed = $this->confirm('Are you sure with this order?');
        if (! $confirmed) {
            $this->warn('You have aborted MFA configurations');

            return Command::SUCCESS;
        }

        // Save the MFA Options selected
        $success = $this->appSettingsManager->setMfaConfig(true, $allowApiManagement, ...$this->convertToEnums($selectedMfaSteps));

        if (! $success) {
            $this->error('Unable to save MFA configurations');

            return Command::FAILURE;
        }

        $this->printMfaEnabledSuccess($selectedMfaSteps);

        return Command::SUCCESS;
    }

    private function getMfaOrderInput(int $totalOptions, array $allMfaOptions): array
    {
        $selectedMfaSteps = [];
        foreach (range(1, $totalOptions) as $i) {
            $ordinal = ConversionHelper::numberToOrdinal($i);

            $blankNote = $i > 1 ? '(Leave as blank to stop adding)' : '';
            $mfaOption = $this->ask("Enter the name of the $ordinal MFA method $blankNote");
            $mfaOption = Str::lower($mfaOption);

            // There must be at least one MFA method inputted
            if ($i === 1 && ! $mfaOption) {
                $this->error('You must have at least one MFA method');

                return [];
            }

            // We stop if the user inputs blank after the first input
            if ($i > 1 && ! $mfaOption) {
                break;
            }

            // We stop if the user inputs the same MfaMethod
            if (in_array($mfaOption, $selectedMfaSteps)) {
                $this->error('You have entered a duplicate MFA method name');

                return [];
            }

            // We stop if the user inputs an invalid MfaMethod value
            if (! in_array($mfaOption, $allMfaOptions)) {
                $this->error('Invalid MFA method name...');

                return [];
            }

            $selectedMfaSteps[] = $mfaOption;
        }

        return $selectedMfaSteps;
    }

    private function getAllMfaMethods(): array
    {
        $registeredMfaClasses = config('auth.mfa_methods');
        $mfaVerificationMethods = [];
        foreach ($registeredMfaClasses as $class) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $factor */
            $factor = resolve($class);
            $mfaVerificationMethods[] = $factor->verificationMethod()->value;
        }

        return $mfaVerificationMethods;
    }

    private function printAllAvailableMfaMethods(): void
    {
        $this->info('These are the current Multi-Factor Authentication methods available');

        $registeredMfaClasses = config('auth.mfa_methods');
        $mfaVerificationMethods = [];
        foreach ($registeredMfaClasses as $class) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $factor */
            $factor = resolve($class);
            $verificationMethodName = Str::title(Str::replace('_', ' ', $factor->verificationMethod()->value));

            // Build the description
            $isDeliveryBased = is_subclass_of($class, DeliveryVerificationMethod::class);
            $description = "Use the $verificationMethodName app to mobile app to generate codes";
            if ($isDeliveryBased) {
                $description = "Receive a one-time code via $verificationMethodName";
            }
            $mfaVerificationMethods[] = [$factor->verificationMethod()->value, $description];
        }

        $this->table(['Name', 'Description'], $mfaVerificationMethods);
        $this->newLine();
    }

    private function printSelectedMfaOrder(array $mfaSteps): void
    {
        $selected = [];
        foreach ($mfaSteps as $key => $value) {
            $selected[] = [ConversionHelper::numberToOrdinal($key + 1), $value];
        }

        $this->table(['Order', 'MFA Methods'], $selected);
    }

    private function printMfaEnabledSuccess(array $mfaSteps): void
    {
        $this->info('You have successfully enabled MFA configurations');
        $steps = implode(' => ', $mfaSteps);
        $this->table(['Status', 'Steps'], [['Enabled', $steps]]);
    }

    private function convertToEnums(array $mfaSteps): array
    {
        return array_map(fn ($val) => VerificationMethod::from($val), $mfaSteps);
    }
}
