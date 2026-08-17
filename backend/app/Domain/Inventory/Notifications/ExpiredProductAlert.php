<?php

namespace App\Domain\Inventory\Notifications;

use App\Domain\Inventory\Models\ProductBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ExpiredProductAlert extends Notification implements ShouldQueue
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
            ->subject('Expired products in your inventory')
            ->line('The following batches have expired and should be removed from sellable stock:');

        foreach ($this->batches as $batch) {
            $message->line("- {$batch->product->name} (batch {$batch->batch_number}): expired {$batch->expiry_date->toFormattedDateString()}, {$batch->quantity} units remaining");
        }

        return $message->action('Review inventory', route('inventory.dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Expired products',
            'message' => $this->batches->count() === 1
                ? "1 batch of \"{$this->batches->first()->product->name}\" has expired."
                : "{$this->batches->count()} batches have expired and should be removed from sellable stock.",
            'url' => route('inventory.dashboard'),
            'icon' => 'x-circle',
        ];
    }
}
