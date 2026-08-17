<?php

namespace App\Domain\Sales\Notifications;

use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SaleReturnApprovedNotification extends Notification implements ShouldQueue
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
            ->subject("Return {$this->saleReturn->return_number} approved")
            ->line("Return {$this->saleReturn->return_number} was approved and a refund of {$this->saleReturn->refund_amount} was recorded.")
            ->action('View return', route('sales.returns.show', $this->saleReturn->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Return approved',
            'message' => "{$this->saleReturn->return_number} approved — refund of {$this->saleReturn->refund_amount} recorded.",
            'url' => route('sales.returns.show', $this->saleReturn->id),
            'icon' => 'check-circle',
        ];
    }
}
