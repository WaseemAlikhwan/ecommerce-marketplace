<?php

namespace App\Notifications;

use App\Models\VendorOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelledVendorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public VendorOrder $vendorOrder,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Vendor order cancelled').' '.$this->vendorOrder->public_code)
            ->line(__('Vendor order :code was cancelled.', [
                'code' => $this->vendorOrder->public_code,
            ]))
            ->action(__('View order'), route('vendor.orders.show', $this->vendorOrder));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_cancelled',
            'audience' => 'vendor',
            'vendor_order_id' => $this->vendorOrder->id,
            'parent_order_id' => $this->vendorOrder->parent_order_id,
            'public_code' => $this->vendorOrder->public_code,
            'message' => __('Vendor order :code was cancelled.', [
                'code' => $this->vendorOrder->public_code,
            ]),
        ];
    }
}
