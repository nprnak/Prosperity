<?php

namespace Modules\ApprovalManagement\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;

/**
 * Applicant-facing update when internal verification/review stages progress.
 */
class ApplicationStageProgressedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ShareApplication $application) {}

    public function via(object $notifiable): array
    {
        // Mail is routed to the applicant contact address; the linked user gets
        // an in-app entry for the notification center.
        return $notifiable instanceof User ? ['database'] : ['mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Application Status Updated',
            'message' => 'Application '.$this->application->application_number
                .' moved to '.$this->application->status->labelEn().'.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Application Status Updated')
            ->line('Your share application has moved to the next verification stage.')
            ->line('Application Number: '.$this->application->application_number)
            ->line('Current Status: '.$this->application->status->labelEn())
            ->line($this->stageHint($this->application->status));
    }

    private function stageHint(ApplicationStatus $status): string
    {
        return match ($status) {
            ApplicationStatus::Verified => 'Verifier completed stage 1. The application is now with a reviewer.',
            ApplicationStatus::Reviewed => 'Reviewer completed stage 2. The application is now with an approver.',
            default => 'Your application is progressing through the review workflow.',
        };
    }
}
