<?php

namespace App\Notifications;

use App\Models\ShareApplication;
use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ShareApplication $application, private readonly ?Voucher $voucher)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
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
