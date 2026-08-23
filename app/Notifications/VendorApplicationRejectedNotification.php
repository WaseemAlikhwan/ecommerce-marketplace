<?php

namespace App\Notifications;

use App\Models\VendorApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorApplicationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VendorApplication $application) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Your vendor application was not approved'))
            ->line(__('Your vendor application for :name was rejected.', [
                'name' => $this->application->store_name,
            ]));

        if (filled($this->application->rejection_reason)) {
            $mail->line(__('Reason').': '.$this->application->rejection_reason);
        }

        $mail->action(__('View application'), route('account.vendor-application'));

        return $mail;
    }
}
