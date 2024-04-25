<?php

namespace App\Console\Commands;

use App\Enums\MfaOption;
use App\Services\AppSettingsManager;
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
    protected $signature = 'app:mfa';

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
        $allMfaOptions = ConversionHelper::enumToArray(MfaOption::class);
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
            $mfaOption = $this->ask("Enter the name of the $ordinal MFA option $blankNote");
            $mfaOption = Str::lower($mfaOption);

            // There must be at least one MFA option inputted
            if ($i === 1 && ! $mfaOption) {
                $this->error('You must have at least one MFA option');

                return [];
            }

            // We stop if the user inputs blank after the first input
            if ($i > 1 && ! $mfaOption) {
                break;
            }

            // We stop if the user inputs the same MfaMethod
            if (in_array($mfaOption, $selectedMfaSteps)) {
                $this->error('You have entered a duplicate MFA option name');

                return [];
            }

            // We stop if the user inputs an invalid MfaMethod value
            if (! in_array($mfaOption, $allMfaOptions)) {
                $this->error('Invalid MFA option name...');

                return [];
            }

            $selectedMfaSteps[] = $mfaOption;
        }

        return $selectedMfaSteps;
    }

    private function printAllAvailableMfaMethods(): void
    {
        $this->info('These are the current Multi-Factor Authentication options available');
        $options = [
            [MfaOption::GOOGLE_AUTHENTICATOR->value, 'Use the Google Authenticator Mobile App to generate codes'],
            [MfaOption::EMAIL_CHANNEL->value, 'Receive a one-time code via email'],
            [MfaOption::SMS_CHANNEL->value, 'Receive a one-time code via SMS'],
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
        return array_map(fn ($val) => MfaOption::from($val), $mfaSteps);
    }
}
