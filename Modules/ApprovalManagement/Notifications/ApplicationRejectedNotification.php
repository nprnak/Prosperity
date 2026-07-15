<?php

namespace Modules\ApprovalManagement\Notifications;

use Modules\ApplicationManagement\Models\ShareApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationRejectedNotification extends Notification implements ShouldQueue
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
            ->subject('Application Rejected')
            ->line('Your share application has been rejected.')
            ->line('Reason: '.($this->application->rejection_reason ?: 'Not provided'));
    }
}
