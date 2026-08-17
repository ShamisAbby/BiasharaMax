<?php

namespace App\Domain\Inventory\Notifications;

use App\Domain\Inventory\Models\InventoryImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BulkImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly InventoryImportLog $importLog,
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
            ->subject('Product import completed')
            ->line("Your import finished: {$this->importLog->success_count} of {$this->importLog->total_rows} products imported successfully.");

        if ($this->importLog->failure_count > 0) {
            $message->line("{$this->importLog->failure_count} rows failed — download the error report for details.");
        }

        return $message->action('View products', route('inventory.products.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Product import completed',
            'message' => "{$this->importLog->success_count} of {$this->importLog->total_rows} products imported successfully.".
                ($this->importLog->failure_count > 0 ? " {$this->importLog->failure_count} rows failed." : ''),
            'url' => route('inventory.products.index'),
            'icon' => 'arrow-up-tray',
        ];
    }
}
