<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public string $code) {}

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
    public function toMail(object $notifiable): array|MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.otp_subject'))
            ->greeting(__('app.otp_greeting'))
            ->line(__('app.otp_line1'))
            ->line($this->code)
            ->line(__('app.otp_line2'))
            ->salutation(__('app.otp_salutation'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'code' => $this->code,
        ];
    }
}
