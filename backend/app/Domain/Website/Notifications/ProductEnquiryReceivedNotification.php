<?php

namespace App\Domain\Website\Notifications;

use App\Domain\Website\Models\ProductEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductEnquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProductEnquiry $enquiry,
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
        $productName = $this->enquiry->product?->name ?? 'a general enquiry';

        return (new MailMessage)
            ->subject("New product enquiry from {$this->enquiry->name}")
            ->line("You have a new enquiry about: {$productName}")
            ->line($this->enquiry->message)
            ->line("Contact: {$this->enquiry->name} ({$this->enquiry->email}, {$this->enquiry->phone})")
            ->action('View Enquiry', route('website.enquiries.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New product enquiry',
            'message' => "{$this->enquiry->name} asked about {$this->enquiry->product?->name}",
            'url' => route('website.enquiries.index'),
            'icon' => 'question-mark-circle',
        ];
    }
}
