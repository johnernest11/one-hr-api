<?php

namespace Tests\Unit;

use App\Enums\AppEnvironment;
use App\Enums\Role;
use App\Listeners\LogEventListener;
use App\Models\User;
use App\Notifications\EmailSystemAlertNotification;
use App\Notifications\SlackSystemAlertNotification;
use Config;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Notification;
use Tests\TestCase;

class LogEventListenerTest extends TestCase
{
    use RefreshDatabase;

    private MessageLogged $loggedEvent;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->user = $this->produceUsers(1, [], false, Role::SYSTEM_SUPPORT);
        Notification::fake();
        $this->loggedEvent = new MessageLogged('warning', 'for testing', ['test' => 1]);
        Config::set('logging.event_listener_environments', [AppEnvironment::TESTING->value]);
    }

    public function test_it_can_send_system_alert_to_slack_once(): void
    {
        $this->produceUsers(2, [], false, Role::SYSTEM_SUPPORT);
        $logEventLister = new LogEventListener();
        $logEventLister->handle($this->loggedEvent);

        // Regardless of how may system support users there are, Slack notifications are only send once
        Notification::assertSentTimes(SlackSystemAlertNotification::class, 1);
    }

    /**
     * @throws Exception
     */
    public function test_it_can_send_system_alerts_to_email_if_enabled(): void
    {
        // We add another system support user, the total is 2 now
        $this->produceUsers(1, [], false, Role::SYSTEM_SUPPORT);

        Config::set('logging.enable_email_dev_alerts', true);

        $logEventLister = new LogEventListener();
        $logEventLister->handle($this->loggedEvent);

        // Email notifications are sent to each system support user
        $users = User::all();
        foreach ($users as $user) {
            Notification::assertSentTo($user, EmailSystemAlertNotification::class);
        }
    }

    /**
     * @throws Exception
     */
    public function test_it_can_send_system_alerts_to_email_if_disabled(): void
    {
        // We add another system support user, the total is 2 now
        $this->produceUsers(1, [], false, Role::SYSTEM_SUPPORT);

        Config::set('logging.enable_email_dev_alerts', false);

        $logEventLister = new LogEventListener();
        $logEventLister->handle($this->loggedEvent);

        $users = User::all();
        foreach ($users as $user) {
            Notification::assertNotSentTo($user, EmailSystemAlertNotification::class);
        }
    }
}
