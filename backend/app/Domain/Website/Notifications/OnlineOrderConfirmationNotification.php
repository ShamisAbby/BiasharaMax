<?php

namespace App\Domain\Website\Notifications;

use App\Domain\Sales\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnlineOrderConfirmationNotification extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order confirmed — {$this->sale->sale_number}")
            ->greeting('Thank you for your order!')
            ->line("Order {$this->sale->sale_number} for {$this->sale->total_amount} has been received.")
            ->when(
                $this->sale->payment_status !== Sale::PAYMENT_STATUS_PAID,
                fn ($message) => $message->line('Payment is due on delivery/pickup.'),
            )
            ->when($this->sale->delivery_address, fn ($message) => $message->line("Delivery address: {$this->sale->delivery_address}"));
    }
}
