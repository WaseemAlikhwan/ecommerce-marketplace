<?php

namespace App\Notifications;

use App\Models\VendorOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorOrderReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public VendorOrder $vendorOrder,
        public string $grandTotalLabel,
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
            ->subject(__('New vendor order').' '.$this->vendorOrder->public_code)
            ->line(__('You received a new order :code.', [
                'code' => $this->vendorOrder->public_code,
            ]))
            ->line(__('COD due').': '.$this->grandTotalLabel)
            ->action(__('View order'), route('vendor.orders.show', $this->vendorOrder));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vendor_order_received',
            'vendor_order_id' => $this->vendorOrder->id,
            'public_code' => $this->vendorOrder->public_code,
            'grand_total_label' => $this->grandTotalLabel,
            'message' => __('You received a new order :code.', [
                'code' => $this->vendorOrder->public_code,
            ]),
        ];
    }
}
