<?php

namespace App\Domain\Website\Notifications;

use App\Domain\Sales\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnlineOrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Sale $sale,
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
            ->subject("New online order {$this->sale->sale_number}")
            ->line("A new online order of {$this->sale->total_amount} was placed.")
            ->when($this->sale->delivery_address, fn ($message) => $message->line("Delivery address: {$this->sale->delivery_address}"))
            ->action('View Order', route('sales.orders.show', $this->sale->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New online order',
            'message' => "Order {$this->sale->sale_number} — {$this->sale->total_amount}",
            'url' => route('sales.orders.show', $this->sale->id),
            'icon' => 'shopping-cart',
        ];
    }
}
