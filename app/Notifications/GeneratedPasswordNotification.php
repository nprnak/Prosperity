<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class GeneratedPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $resetUrl, private ?string $creatorName = null)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $appName = config('app.name');

        $mail = (new MailMessage)
            ->subject("[{$appName}] Set up your account")
            ->greeting("Hello " . ($notifiable->name ?? ''))
            ->line("An account has been created for you on {$appName}.")
            ->line('To set a secure password for your account, follow the link below. The link will expire shortly for security.')
            ->action('Set your password', $this->resetUrl)
            ->line('If you did not expect this email, please contact your administrator.');

        if ($this->creatorName) {
            $mail->line('Created by: ' . $this->creatorName);
        }

        return $mail;
    }
}
