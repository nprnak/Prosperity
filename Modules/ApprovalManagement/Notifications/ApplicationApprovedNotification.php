<?php

namespace Modules\ApprovalManagement\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\VoucherManagement\Models\Voucher;

class ApplicationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ShareApplication $application, private readonly ?Voucher $voucher) {}

    public function via(object $notifiable): array
    {
        // mail goes to the applicant's contact address via an on-demand route;
        // the linked user account gets the in-app copy.
        return $notifiable instanceof User ? ['database'] : ['mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Application Approved',
            'message' => 'Application '.$this->application->application_number.' has been approved.'
                .($this->voucher ? ' Voucher '.$this->voucher->voucher_number.' was issued.' : ''),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Application Approved')
            ->line('Your share application has been approved.')
            ->line('Application Number: '.$this->application->application_number);

        if ($this->voucher) {
            $mail->line('Voucher Number: '.$this->voucher->voucher_number);
            if ($this->voucher->pdf_path && Storage::disk('private')->exists($this->voucher->pdf_path)) {
                $mail->attach(Storage::disk('private')->path($this->voucher->pdf_path), [
                    'as' => 'voucher-'.$this->voucher->voucher_number.'.pdf',
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $mail;
    }
}
