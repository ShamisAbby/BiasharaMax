<?php

namespace App\Domain\Inventory\Notifications;

use App\Domain\Inventory\Models\Inventory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Inventory>  $items
     */
    public function __construct(
        private readonly Collection $items,
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
        $count   = $this->items->count();
        $subject = $count === 1
            ? "⚠️ Low stock: {$this->items->first()->product->name}"
            : "⚠️ Low stock alert: {$count} products need restocking";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Low Stock Warning')
            ->line(
                $count === 1
                    ? "The following product has dropped below 10 units and needs restocking:"
                    : "The following {$count} products have dropped below 10 units and need restocking:"
            );

        foreach ($this->items as $item) {
            $qty       = (float) $item->quantity;
            $warehouse = $item->warehouse?->name ?? 'Main Warehouse';
            $message->line("• **{$item->product->name}** — {$qty} unit(s) remaining at {$warehouse}");
        }

        return $message
            ->line('Please restock these items as soon as possible to avoid disruption to sales.')
            ->action('View Inventory', route('inventory.dashboard'))
            ->salutation('BiasharaMax Inventory Alerts');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $count = $this->items->count();

        return [
            'title'   => 'Low stock alert',
            'message' => $count === 1
                ? "\"{$this->items->first()->product->name}\" has only {$this->items->first()->quantity} unit(s) left — restock soon."
                : "{$count} products have dropped below 10 units and need restocking.",
            'url'     => route('inventory.dashboard'),
            'icon'    => 'exclamation-triangle',
        ];
    }
}
