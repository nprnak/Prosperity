<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TwoFactorCode extends Notification
{
    use Queueable;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your two-factor verification code')
            ->line('Your verification code is:')
            ->displayAddress('no-reply@'.config('app.domain', request()->getHost()))
            ->line('')
            ->line('Code: ' . $this->code)
            ->line('This code will expire in 10 minutes.')
            ->salutation('');
    }
}
