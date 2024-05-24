<?php

namespace Tests\Unit\Commands;

use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneExpiredMfaAttemptsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    public function test_it_should_delete_expired_tokens(): void
    {
        $user = $this->produceUsers();
        $expiredTotal = 3;
        $notExpiredTotal = 1;
        $mfaAttempts = [];

        foreach (range(1, $expiredTotal + $notExpiredTotal) as $i) {
            $attempt = [
                'user_id' => $user->id,
                'token' => fake()->uuid(),
                'steps' => json_encode([]),
                'auth_metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($i <= $expiredTotal) {
                $attempt['expires_at'] = now()->subHours(2);
            } else {
                $attempt['expires_at'] = now()->addHours(2);
            }

            $mfaAttempts[] = $attempt;
        }

        DB::table('mfa_attempts')->insert($mfaAttempts);

        $this->artisan('mfa:prune-expired-attempts');

        $count = DB::table('mfa_attempts')->count('id');
        $this->assertEquals($notExpiredTotal, $count);
    }
}
