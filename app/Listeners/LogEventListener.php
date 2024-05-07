<?php

namespace App\Listeners;

use App\Enums\Permission;
use App\Models\User;
use App\Notifications\EmailSystemAlertNotification;
use App\Notifications\SlackSystemAlertNotification;
use Illuminate\Log\Events\MessageLogged;
use Notification;

class LogEventListener
{
    public const LOG_LEVELS = [
        'debug' => 1,
        'info' => 2,
        'notice' => 3,
        'warning' => 4,
        'error' => 5,
        'critical' => 6,
        'alert' => 7,
        'emergency' => 8,
    ];

    /**
     * Handle the event.
     */
    public function handle(MessageLogged $event): void
    {
        if (! in_array(app()->environment(), $this->getEnvironmentsToLog())) {
            return;
        }

        // Check the logging level set in config
        if (self::LOG_LEVELS[$event->level] < self::LOG_LEVELS[config('logging.event_listener_level')]) {
            return;
        }

        // Send a notification to the Slack Dev channel
        $webhookUrl = config('integrations.slack.webhooks.dev_alerts');
        Notification::route('slack', $webhookUrl)
            ->notify(new SlackSystemAlertNotification($event->level, $event->message));

        /**
         * Send an email notification to all users with the system alert permission.
         * This config is disabled by default. Be wary of turning the flag on
         * for sending error messages via email
         */
        if (! config('logging.enable_email_dev_alerts')) {
            return;
        }

        $users = User::permission([Permission::RECEIVE_SYSTEM_ALERTS->value])->cursor();
        /** @var User $user */
        foreach ($users as $user) {
            // We send email alerts to every System Support Role
            $user->notify(new EmailSystemAlertNotification($event->level, $event->message));
        }
    }

    private function getEnvironmentsToLog(): array
    {
        // Only send email notifications when in prod, uat, or development
        return config('logging.event_listener_environments');
    }
}
