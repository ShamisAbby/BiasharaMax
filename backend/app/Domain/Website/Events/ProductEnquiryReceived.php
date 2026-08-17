<?php

namespace App\Domain\Website\Events;

use App\Domain\Website\Models\ProductEnquiry;
use Illuminate\Foundation\Events\Dispatchable;

class ProductEnquiryReceived
{
    use Dispatchable;

    public function __construct(
        public readonly ProductEnquiry $enquiry,
    ) {}
}
