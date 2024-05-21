<?php

namespace Tests\Unit\Commands;

use App\Models\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetUpMfaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic unit test example.
     */
    public function test_it_can_disable_mfa_options(): void
    {
        $this->artisan('mfa:setup')
            ->expectsConfirmation('Turn-on Multi-Factor Authentication?')
            ->expectsConfirmation('Allow MFA configurations to be managed via API endpoints?')
            ->assertOk();

        $mfaConfig = json_decode(AppSettings::where('name', 'mfa')->first()->value, true);
        $this->assertFalse($mfaConfig['enabled']);
    }

    public function test_it_can_enable_mfa_and_all_steps(): void
    {
        $methods = ['email_channel', 'google_authenticator'];

        $this->artisan('mfa:setup')
            ->expectsConfirmation('Turn-on Multi-Factor Authentication?', 'yes')
            ->expectsConfirmation('Allow MFA configurations to be managed via API endpoints?', 'yes')
            ->expectsQuestion('Enter the name of the 1st MFA method ', $methods[0])
            ->expectsQuestion('Enter the name of the 2nd MFA method (Leave as blank to stop adding)', $methods[1])
            ->expectsConfirmation('Are you sure with this order?', 'yes')
            ->assertOk();

        $mfaConfig = json_decode(AppSettings::where('name', 'mfa')->first()->value, true);
        $this->assertTrue($mfaConfig['enabled']);
        $this->assertEquals($methods, $mfaConfig['steps']);
        $this->assertTrue($mfaConfig['allow_api_management']);
    }

    public function test_it_will_stop_adding_step_if_left_blank(): void
    {
        $methods = ['google_authenticator'];

        $this->artisan('mfa:setup')
            ->expectsConfirmation('Turn-on Multi-Factor Authentication?', 'yes')
            ->expectsConfirmation('Allow MFA configurations to be managed via API endpoints?', 'yes')
            ->expectsQuestion('Enter the name of the 1st MFA method ', $methods[0])
            ->expectsQuestion('Enter the name of the 2nd MFA method (Leave as blank to stop adding)', '')
            ->expectsConfirmation('Are you sure with this order?', 'yes')
            ->assertOk();

        $mfaConfig = json_decode(AppSettings::where('name', 'mfa')->first()->value, true);
        $this->assertTrue($mfaConfig['enabled']);
        $this->assertEquals($methods, $mfaConfig['steps']);
    }

    public function test_it_will_stop_if_entered_mfa_method_is_incorrect(): void
    {
        $this->artisan('mfa:setup')
            ->expectsConfirmation('Turn-on Multi-Factor Authentication?', 'yes')
            ->expectsConfirmation('Allow MFA configurations to be managed via API endpoints?', 'yes')
            ->expectsQuestion('Enter the name of the 1st MFA method ', 'non-existent')
            ->assertFailed();
    }

    public function test_it_will_stop_if_entered_mfa_method_is_duplicate(): void
    {
        $methods = ['google_authenticator'];

        $this->artisan('mfa:setup')
            ->expectsConfirmation('Turn-on Multi-Factor Authentication?', 'yes')
            ->expectsConfirmation('Allow MFA configurations to be managed via API endpoints?', 'yes')
            ->expectsQuestion('Enter the name of the 1st MFA method ', $methods[0])
            ->expectsQuestion('Enter the name of the 2nd MFA method (Leave as blank to stop adding)', $methods[0])
            ->assertFailed();
    }
}
