<?php

namespace Modules\ApplicantManagement\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\ApplicantManagement\Models\Profile;

class ProfileApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Profile $applicant) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Profile Approved')
            ->line('Your applicant profile has been approved.')
            ->line('You can now apply for shares from your dashboard.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Profile Approved',
            'message' => 'Your applicant profile has been approved. You can now apply for shares.',
        ];
    }
}
