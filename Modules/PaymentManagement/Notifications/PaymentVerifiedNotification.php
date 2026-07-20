<?php

namespace Modules\PaymentManagement\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\ApplicationManagement\Models\ShareApplication;

class PaymentVerifiedNotification extends Notification implements ShouldQueue
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
            'title' => 'Payment Verified',
            'message' => 'Payment verified for application '.$this->application->application_number.'.',
        ];
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
