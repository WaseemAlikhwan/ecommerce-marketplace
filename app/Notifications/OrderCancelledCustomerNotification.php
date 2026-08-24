<?php

namespace App\Notifications;

use App\Models\ParentOrder;
use App\Models\VendorOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelledCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ParentOrder $parentOrder,
        public ?VendorOrder $vendorOrder = null,
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
            ->subject($this->subjectLine())
            ->line($this->bodyLine())
            ->action(__('View order'), route('account.orders.show', $this->parentOrder));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_cancelled',
            'audience' => 'customer',
            'parent_order_id' => $this->parentOrder->id,
            'parent_public_code' => $this->parentOrder->public_code,
            'vendor_order_id' => $this->vendorOrder?->id,
            'vendor_public_code' => $this->vendorOrder?->public_code,
            'message' => $this->bodyLine(),
        ];
    }

    private function subjectLine(): string
    {
        if ($this->vendorOrder !== null) {
            return __('Vendor order cancelled').' '.$this->vendorOrder->public_code;
        }

        return __('Order cancelled').' '.$this->parentOrder->public_code;
    }

    private function bodyLine(): string
    {
        if ($this->vendorOrder !== null) {
            return __('Your vendor shipment :code was cancelled.', [
                'code' => $this->vendorOrder->public_code,
            ]);
        }

        return __('Your order :code was cancelled.', [
            'code' => $this->parentOrder->public_code,
        ]);
    }
}
