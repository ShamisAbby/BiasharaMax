<?php

namespace App\Domain\Inventory\Notifications;

use App\Domain\Inventory\Models\StockTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockTransferCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly StockTransfer $transfer,
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
            ->subject("Stock transfer {$this->transfer->transfer_number} completed")
            ->line("Transfer {$this->transfer->transfer_number} from {$this->transfer->fromWarehouse->name} to {$this->transfer->toWarehouse->name} has been received.")
            ->action('View transfers', route('inventory.stock-transfers.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Stock transfer completed',
            'message' => "Transfer {$this->transfer->transfer_number} from {$this->transfer->fromWarehouse->name} to {$this->transfer->toWarehouse->name} has been received.",
            'url' => route('inventory.stock-transfers.index'),
            'icon' => 'truck',
        ];
    }
}
