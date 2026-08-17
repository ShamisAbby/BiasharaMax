<?php

namespace App\Domain\Website\Services;

use App\Domain\Website\Events\ProductEnquiryReceived;
use App\Domain\Website\Models\ProductEnquiry;
use App\Domain\Website\Notifications\ProductEnquiryRepliedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class ProductEnquiryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProductEnquiry
    {
        $enquiry = ProductEnquiry::create($data);

        ProductEnquiryReceived::dispatch($enquiry);

        return $enquiry;
    }

    public function reply(ProductEnquiry $enquiry, string $reply): void
    {
        $enquiry->update([
            'reply' => $reply,
            'status' => ProductEnquiry::STATUS_RESPONDED,
            'responded_at' => Carbon::now(),
        ]);

        if ($enquiry->email) {
            Notification::route('mail', $enquiry->email)
                ->notify(new ProductEnquiryRepliedNotification($enquiry));
        }
    }

    public function updateStatus(ProductEnquiry $enquiry, string $status): void
    {
        $enquiry->update(['status' => $status]);
    }
}
