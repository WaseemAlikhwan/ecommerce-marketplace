<?php

namespace App\Notifications;

use App\Enums\VendorOrderStatus;
use App\Models\VendorOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorOrderStatusChangedVendorNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject($this->subjectLine())
            ->line($this->bodyLine())
            ->action(__('View order'), route('vendor.orders.show', $this->vendorOrder));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'vendor_order_status_changed',
            'audience' => 'vendor',
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
            VendorOrderStatus::Confirmed => __('Order confirmed').' '.$this->vendorOrder->public_code,
            VendorOrderStatus::Shipped => __('Order shipped').' '.$this->vendorOrder->public_code,
            VendorOrderStatus::Delivered => __('Order delivered').' '.$this->vendorOrder->public_code,
            default => __('Order update').' '.$this->vendorOrder->public_code,
        };
    }

    private function bodyLine(): string
    {
        return match ($this->status) {
            VendorOrderStatus::Confirmed => __('You confirmed vendor order :code.', [
                'code' => $this->vendorOrder->public_code,
            ]),
            VendorOrderStatus::Shipped => __('You marked vendor order :code as shipped.', [
                'code' => $this->vendorOrder->public_code,
            ]),
            VendorOrderStatus::Delivered => __('You marked vendor order :code as delivered.', [
                'code' => $this->vendorOrder->public_code,
            ]),
            default => __('Vendor order :code was updated.', [
                'code' => $this->vendorOrder->public_code,
            ]),
        };
    }
}
