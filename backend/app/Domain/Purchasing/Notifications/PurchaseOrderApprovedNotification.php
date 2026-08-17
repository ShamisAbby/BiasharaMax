<?php

namespace App\Domain\Purchasing\Notifications;

use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PurchaseOrder $purchaseOrder,
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
        return (new MailMessage)
            ->subject("Purchase order {$this->purchaseOrder->po_number} approved")
            ->line("Purchase order {$this->purchaseOrder->po_number} for {$this->purchaseOrder->supplier->name} has been approved.")
            ->action('View purchase order', route('purchasing.orders.show', $this->purchaseOrder->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Purchase order approved',
            'message' => "{$this->purchaseOrder->po_number} for {$this->purchaseOrder->supplier->name} has been approved.",
            'url' => route('purchasing.orders.show', $this->purchaseOrder->id),
            'icon' => 'check-circle',
        ];
    }
}
