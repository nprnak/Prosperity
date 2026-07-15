<?php

namespace Modules\PaymentManagement\Notifications;

use Modules\ApplicationManagement\Models\ShareApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ShareApplication $application)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Verified')
            ->line('Payment has been verified for your share application.')
            ->line('Application Number: '.$this->application->application_number)
            ->line('Status: '.$this->application->status);
    }
}
