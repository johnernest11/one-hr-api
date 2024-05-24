<?php

namespace App\Notifications;

use App\Enums\Queue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $otp;

    private int $expirationInMinutes;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, int $expirationInMinutes)
    {
        $this->otp = $otp;
        $this->onQueue(Queue::OTP->value);
        $this->expirationInMinutes = $expirationInMinutes;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $firstName = $notifiable->userProfile->first_name;
        $body = "Use the six-digit one-time password below to complete the sign-in process. Note that this will expire in $this->expirationInMinutes minutes.";
        $subject = config('app.name').' - OTP Verification';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hi, '.$firstName.'!')
            ->line($body)
            ->line($this->otp)
            ->line('Never share your OTPs with anyone. Our employees will never ask this from you.');
    }
}
