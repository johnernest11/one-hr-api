<?php

namespace App\Console\Commands;

use App\Enums\MfaMethod;
use App\Services\AppSettings\AppSettingsManager;
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
    protected $signature = 'app:set-mfa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set-up MFA configurations';

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
        $status = $this->choice('Turn-on Multi-Factor Authentication?', [1 => 'Yes', 2 => 'No']);

        if (Str::lower($status) === 'no') {
            $this->appSettingsManager->setMfaConfig(false);
            $this->info('You have disabled multi-factor authentication');

            return Command::SUCCESS;
        }

        $this->printAllAvailableMfaMethods();

        // Build the options for selection
        $allMfaOptions = ConversionHelper::enumToArray(MfaMethod::class);
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
        $success = $this->appSettingsManager->setMfaConfig(true, ...$this->convertToEnums($selectedMfaSteps));

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

            if (! in_array($mfaOption, $allMfaOptions)) {
                $this->error('Invalid MFA method name...');

                return [];
            }

            $selectedMfaSteps[] = $mfaOption;
        }

        return $selectedMfaSteps;
    }

    private function printAllAvailableMfaMethods(): void
    {
        $this->info('These are the current Multi-Factor Authentication methods available');
        $options = [
            [MfaMethod::GOOGLE_AUTHENTICATOR->value, 'Use the Google Authenticator Mobile App to generate codes'],
            [MfaMethod::EMAIL_CHANNEL->value, 'Receive a one-time code via email'],
            [MfaMethod::SMS_CHANNEL->value, 'Receive a one-time code via SMS'],
        ];
        $this->table(['Name', 'Description'], $options);
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
        return array_map(fn ($val) => MfaMethod::from($val), $mfaSteps);
    }
}
