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
        return ['mail', 'database'];
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vendor_application_rejected',
            'vendor_application_id' => $this->application->id,
            'store_name' => $this->application->store_name,
            'message' => __('Your vendor application for :name was rejected.', [
                'name' => $this->application->store_name,
            ]),
        ];
    }
}
