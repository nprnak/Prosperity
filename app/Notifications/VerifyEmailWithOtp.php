<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailWithOtp extends VerifyEmail
{
    public function __construct(protected string $otp) {}

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Email Address')
            ->line('Your email verification code is:')
            ->line("**{$this->otp}**")
            ->line('Enter this code on the verification page, or click the button below.')
            ->action('Verify Email Address', $url)
            ->line('The code expires in 10 minutes. If you did not create an account, no further action is required.');
    }
}
