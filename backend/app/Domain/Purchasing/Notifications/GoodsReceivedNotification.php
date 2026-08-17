<?php

namespace App\Domain\Purchasing\Notifications;

use App\Domain\Purchasing\Models\GoodsReceivedNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoodsReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly GoodsReceivedNote $goodsReceivedNote,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $po = $this->goodsReceivedNote->purchaseOrder;

        return (new MailMessage)
            ->subject("Goods received against {$po->po_number}")
            ->line("Goods receipt {$this->goodsReceivedNote->grn_number} was recorded against purchase order {$po->po_number}.")
            ->line("Purchase order status: {$po->status}.")
            ->action('View purchase order', route('purchasing.orders.show', $po->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $po = $this->goodsReceivedNote->purchaseOrder;

        return [
            'title' => 'Goods received',
            'message' => "{$this->goodsReceivedNote->grn_number} recorded against {$po->po_number} ({$po->status}).",
            'url' => route('purchasing.orders.show', $po->id),
            'icon' => 'truck',
        ];
    }
}
