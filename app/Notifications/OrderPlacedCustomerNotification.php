<?php

namespace App\Notifications;

use App\Models\ParentOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $codDueLabels
     */
    public function __construct(
        public ParentOrder $parentOrder,
        public array $codDueLabels,
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
        $dues = $this->codDueLabels === []
            ? __('COD amount will appear on your order.')
            : __('COD due').': '.implode(' · ', $this->codDueLabels);

        return (new MailMessage)
            ->subject(__('Order placed').' '.$this->parentOrder->public_code)
            ->line(__('Your order :code was placed successfully.', [
                'code' => $this->parentOrder->public_code,
            ]))
            ->line($dues)
            ->action(__('View order'), route('account.orders.show', $this->parentOrder));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_placed',
            'parent_order_id' => $this->parentOrder->id,
            'public_code' => $this->parentOrder->public_code,
            'cod_dues' => $this->codDueLabels,
            'message' => __('Your order :code was placed successfully.', [
                'code' => $this->parentOrder->public_code,
            ]),
        ];
    }
}
