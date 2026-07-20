<?php

namespace Modules\ApplicantManagement\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\ApplicantManagement\Models\Profile;

class ProfileReturnedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Profile $applicant) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Profile Needs Changes',
            'message' => 'Your profile has been returned for changes: '.($this->applicant->latest_workflow_remarks ?? 'Not specified'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Profile Needs Changes')
            ->line('Your applicant profile was reviewed and needs changes before it can be approved.')
            ->line('Reason: '.($this->applicant->latest_workflow_remarks ?? 'Not specified'))
            ->line('Please update your profile and submit it again.');
    }
}
