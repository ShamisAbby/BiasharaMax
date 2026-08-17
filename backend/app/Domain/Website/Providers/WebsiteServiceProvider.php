<?php

namespace App\Domain\Website\Providers;

use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Website\Events\ProductEnquiryReceived;
use App\Domain\Website\Listeners\NotifyOnOnlineOrderPlaced;
use App\Domain\Website\Listeners\NotifyOwnerOfProductEnquiry;
use App\Domain\Website\Models\Article;
use App\Domain\Website\Models\BusinessWebsite;
use App\Domain\Website\Models\ProductEnquiry;
use App\Domain\Website\Policies\ArticlePolicy;
use App\Domain\Website\Policies\BusinessWebsitePolicy;
use App\Domain\Website\Policies\ProductEnquiryPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WebsiteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(BusinessWebsite::class, BusinessWebsitePolicy::class);
        Gate::policy(ProductEnquiry::class, ProductEnquiryPolicy::class);
        Gate::policy(Article::class, ArticlePolicy::class);

        Event::listen(ProductEnquiryReceived::class, NotifyOwnerOfProductEnquiry::class);
        Event::listen(SaleCompleted::class, NotifyOnOnlineOrderPlaced::class);
    }
}
