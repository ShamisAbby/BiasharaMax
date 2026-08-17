<?php

namespace App\Domain\Inventory\Notifications;

use App\Domain\Inventory\Models\ProductBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ExpiringSoonAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, ProductBatch>  $batches
     */
    public function __construct(
        private readonly Collection $batches,
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
        $message = (new MailMessage)
            ->subject('Products expiring soon')
            ->line('The following batches are expiring within 30 days:');

        foreach ($this->batches as $batch) {
            $message->line("- {$batch->product->name} (batch {$batch->batch_number}): expires {$batch->expiry_date->toFormattedDateString()}, {$batch->quantity} units remaining");
        }

        return $message->action('View inventory', route('inventory.dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Products expiring soon',
            'message' => $this->batches->count() === 1
                ? "1 batch of \"{$this->batches->first()->product->name}\" expires within 30 days."
                : "{$this->batches->count()} batches are expiring within 30 days.",
            'url' => route('inventory.dashboard'),
            'icon' => 'clock',
        ];
    }
}
