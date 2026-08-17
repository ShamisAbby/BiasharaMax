<?php

namespace App\Domain\Website\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Website\Models\ProductEnquiry;

class ProductEnquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('website.view');
    }

    public function view(User $user, ProductEnquiry $enquiry): bool
    {
        return $user->business_id === $enquiry->business_id && $user->hasPermission('website.view');
    }

    public function manage(User $user, ProductEnquiry $enquiry): bool
    {
        return $user->business_id === $enquiry->business_id && $user->hasPermission('website.manage');
    }
}
