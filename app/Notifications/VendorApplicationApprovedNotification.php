<?php

namespace App\Notifications;

use App\Models\VendorApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorApplicationApprovedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject(__('Your vendor application was approved'))
            ->line(__('Your store :name is ready. You can now open the seller workspace.', [
                'name' => $this->application->store_name,
            ]))
            ->action(__('Open seller workspace'), route('vendor.dashboard'));
    }
}
