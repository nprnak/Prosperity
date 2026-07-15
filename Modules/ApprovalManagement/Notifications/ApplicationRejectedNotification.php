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
        // mail goes to the applicant's contact address via an on-demand route;
        // the linked user account gets the in-app copy.
        return $notifiable instanceof \App\Models\User ? ['database'] : ['mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Application Rejected',
            'message' => 'Application '.$this->application->application_number.' was rejected: '
                .($this->application->rejection_reason ?: 'Not provided'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Application Rejected')
            ->line('Your share application has been rejected.')
            ->line('Reason: '.($this->application->rejection_reason ?: 'Not provided'));
    }
}
