<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;

class PruneExpiredMfaAttempts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mfa:prune-expired-attempts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete database records of MFA attempts that have expired.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        DB::table('mfa_attempts')
            ->where('expires_at', '<', now())
            ->delete();
    }
}
