<?php

namespace App\Console\Commands;

use App\Models\AppSettings;
use Illuminate\Console\Command;
use Str;

class SetMfa extends Command
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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $status = $this->choice('Turn-on Multi-Factor Authentication?', [
            1 => 'Yes',
            2 => 'No',
        ]);

        if (Str::lower($status) === 'no') {
            AppSettings::updateOrCreate(
                ['name' => 'mfa_options'],
                ['value' => json_encode(['is_enabled' => false, 'steps' => []])]
            );

            $this->info('You have disabled multi-factor authentication');

            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }
}
