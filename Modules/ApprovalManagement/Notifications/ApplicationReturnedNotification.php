<?php

namespace Modules\ApprovalManagement\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\ApplicationManagement\Models\ShareApplication;

class ApplicationReturnedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ShareApplication $application) {}

    public function via(object $notifiable): array
    {
        // mail goes to the applicant's contact address via an on-demand route;
        // the linked user account gets the in-app copy.
        return $notifiable instanceof User ? ['database'] : ['mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Application Returned',
            'message' => 'Application '.$this->application->application_number.' was rejected: '
                .($this->application->latest_workflow_remarks ?: $this->application->rejection_reason ?: 'Not provided'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Application Returned')
            ->line('Your share application has been rejected.')
            ->line('Reason: '.($this->application->latest_workflow_remarks ?: $this->application->rejection_reason ?: 'Not provided'));
    }
}
