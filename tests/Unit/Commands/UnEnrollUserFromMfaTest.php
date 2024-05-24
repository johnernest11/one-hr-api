<?php

namespace Tests\Unit\Commands;

use App\Enums\VerificationMethod;
use App\Models\VerificationFactor;
use App\Services\Verification\AppVerificationMethod;
use App\Services\Verification\DeliveryVerificationMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnEnrollUserFromMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    public function test_it_can_un_enroll_a_user_via_email(): void
    {
        $method = VerificationMethod::GOOGLE_AUTHENTICATOR;
        $user = $this->produceUsers();
        $factor = VerificationFactor::factory()->create(['user_id' => $user->id, 'type' => $method]);
        $this->assertNotNull($factor->enrolled_at);

        $registeredMfaClasses = config('auth.mfa_methods');
        $mfaVerificationMethods = [];
        foreach ($registeredMfaClasses as $class) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $verificationMethod */
            $verificationMethod = resolve($class);
            $name = $verificationMethod->verificationMethod()->value;
            $mfaVerificationMethods[] = $name;
            $mfaVerificationMethods[$name] = Str::title(Str::replace('_', ' ', $name));
        }

        $this->artisan('mfa:un-enroll')
            ->expectsQuestion('Enter the email address of the user you want to un-enroll', $user->email)
            ->expectsChoice('Which MFA method should be un-enrolled?', $method->value, $mfaVerificationMethods)
            ->assertOk();

        $factor->refresh();
        $this->assertNull($factor->enrolled_at);
    }
}
