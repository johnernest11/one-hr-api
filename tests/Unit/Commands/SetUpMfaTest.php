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
        $this->artisan('app:set-mfa')
            ->expectsChoice('Turn-on Multi-Factor Authentication?', 'No', [1 => 'Yes', 2 => 'No'])
            ->assertOk();

        $mfaConfig = json_decode(AppSettings::where('name', 'mfa')->first()->value, true);
        $this->assertFalse($mfaConfig['enabled']);
    }

    public function test_it_can_enable_mfa_and_all_steps(): void
    {
        $options = ['email_channel', 'google_authenticator', 'sms_channel'];

        $this->artisan('app:set-mfa')
            ->expectsChoice('Turn-on Multi-Factor Authentication?', 'Yes', [1 => 'Yes', 2 => 'No'])
            ->expectsQuestion('Enter the name of the 1st MFA option ', $options[0])
            ->expectsQuestion('Enter the name of the 2nd MFA option (Leave as blank to stop adding)', $options[1])
            ->expectsQuestion('Enter the name of the 3rd MFA option (Leave as blank to stop adding)', $options[2])
            ->expectsConfirmation('Are you sure with this order?', 'yes')
            ->assertOk();

        $mfaConfig = json_decode(AppSettings::where('name', 'mfa')->first()->value, true);
        $this->assertTrue($mfaConfig['enabled']);
        $this->assertEquals($options, $mfaConfig['steps']);
    }

    public function test_it_will_stop_adding_step_if_left_blank(): void
    {
        $options = ['google_authenticator'];

        $this->artisan('app:set-mfa')
            ->expectsChoice('Turn-on Multi-Factor Authentication?', 'Yes', [1 => 'Yes', 2 => 'No'])
            ->expectsQuestion('Enter the name of the 1st MFA option ', $options[0])
            ->expectsQuestion('Enter the name of the 2nd MFA option (Leave as blank to stop adding)', '')
            ->expectsConfirmation('Are you sure with this order?', 'yes')
            ->assertOk();

        $mfaConfig = json_decode(AppSettings::where('name', 'mfa')->first()->value, true);
        $this->assertTrue($mfaConfig['enabled']);
        $this->assertEquals($options, $mfaConfig['steps']);
    }

    public function test_it_will_stop_if_entered_mfa_option_is_incorrect(): void
    {
        $this->artisan('app:set-mfa')
            ->expectsChoice('Turn-on Multi-Factor Authentication?', 'Yes', [1 => 'Yes', 2 => 'No'])
            ->expectsQuestion('Enter the name of the 1st MFA option ', 'non-existent')
            ->assertFailed();
    }

    public function test_it_will_stop_if_entered_mfa_option_is_duplicate(): void
    {
        $options = ['google_authenticator'];

        $this->artisan('app:set-mfa')
            ->expectsChoice('Turn-on Multi-Factor Authentication?', 'Yes', [1 => 'Yes', 2 => 'No'])
            ->expectsQuestion('Enter the name of the 1st MFA option ', $options[0])
            ->expectsQuestion('Enter the name of the 2nd MFA option (Leave as blank to stop adding)', $options[0])
            ->assertFailed();
    }
}
