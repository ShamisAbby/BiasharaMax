<?php

namespace App\Domain\Sales\Notifications;

use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SaleReturnRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SaleReturn $saleReturn,
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
            ->subject("New return request {$this->saleReturn->return_number}")
            ->line("A return ({$this->saleReturn->return_number}) was requested against sale {$this->saleReturn->sale->sale_number}.")
            ->line("Reason: {$this->saleReturn->reason}")
            ->action('Review return', route('sales.returns.show', $this->saleReturn->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New return request',
            'message' => "{$this->saleReturn->return_number} requested against sale {$this->saleReturn->sale->sale_number}.",
            'url' => route('sales.returns.show', $this->saleReturn->id),
            'icon' => 'arrow-uturn-left',
        ];
    }
}
