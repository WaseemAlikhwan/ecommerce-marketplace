<?php

namespace App\Notifications;

use App\Enums\VendorOrderStatus;
use App\Models\VendorOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorOrderStatusChangedCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public VendorOrder $vendorOrder,
        public VendorOrderStatus $status,
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
        $parent = $this->vendorOrder->parentOrder;

        return (new MailMessage)
            ->subject($this->subjectLine())
            ->line($this->bodyLine())
            ->action(__('View order'), route('account.orders.show', $parent));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vendor_order_status_changed',
            'audience' => 'customer',
            'vendor_order_id' => $this->vendorOrder->id,
            'parent_order_id' => $this->vendorOrder->parent_order_id,
            'public_code' => $this->vendorOrder->public_code,
            'status' => $this->status->value,
            'message' => $this->bodyLine(),
        ];
    }

    private function subjectLine(): string
    {
        return match ($this->status) {
            VendorOrderStatus::Confirmed => __('Vendor order confirmed').' '.$this->vendorOrder->public_code,
            VendorOrderStatus::Shipped => __('Vendor order shipped').' '.$this->vendorOrder->public_code,
            VendorOrderStatus::Delivered => __('Vendor order delivered').' '.$this->vendorOrder->public_code,
            default => __('Order update').' '.$this->vendorOrder->public_code,
        };
    }

    private function bodyLine(): string
    {
        return match ($this->status) {
            VendorOrderStatus::Confirmed => __('Your vendor shipment :code was confirmed.', [
                'code' => $this->vendorOrder->public_code,
            ]),
            VendorOrderStatus::Shipped => __('Your vendor shipment :code was shipped.', [
                'code' => $this->vendorOrder->public_code,
            ]),
            VendorOrderStatus::Delivered => __('Your vendor shipment :code was delivered.', [
                'code' => $this->vendorOrder->public_code,
            ]),
            default => __('Your vendor shipment :code was updated.', [
                'code' => $this->vendorOrder->public_code,
            ]),
        };
    }
}
