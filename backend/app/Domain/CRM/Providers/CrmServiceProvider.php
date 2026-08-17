<?php

namespace App\Domain\CRM\Providers;

use App\Domain\CRM\Listeners\RecalculateLoyaltyTierOnReturn;
use App\Domain\CRM\Listeners\RecalculateLoyaltyTierOnSale;
use App\Domain\CRM\Models\CustomerFeedback;
use App\Domain\CRM\Models\CustomerGroup;
use App\Domain\CRM\Models\CustomerNote;
use App\Domain\CRM\Models\CustomerTag;
use App\Domain\CRM\Models\LoyaltyReward;
use App\Domain\CRM\Models\LoyaltyTier;
use App\Domain\CRM\Models\MarketingCampaign;
use App\Domain\CRM\Policies\CustomerFeedbackPolicy;
use App\Domain\CRM\Policies\CustomerGroupPolicy;
use App\Domain\CRM\Policies\CustomerNotePolicy;
use App\Domain\CRM\Policies\CustomerTagPolicy;
use App\Domain\CRM\Policies\LoyaltyRewardPolicy;
use App\Domain\CRM\Policies\LoyaltyTierPolicy;
use App\Domain\CRM\Policies\MarketingCampaignPolicy;
use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Sales\Events\SaleReturnApproved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(CustomerGroup::class, CustomerGroupPolicy::class);
        Gate::policy(CustomerTag::class, CustomerTagPolicy::class);
        Gate::policy(CustomerNote::class, CustomerNotePolicy::class);
        Gate::policy(LoyaltyTier::class, LoyaltyTierPolicy::class);
        Gate::policy(LoyaltyReward::class, LoyaltyRewardPolicy::class);
        Gate::policy(CustomerFeedback::class, CustomerFeedbackPolicy::class);
        Gate::policy(MarketingCampaign::class, MarketingCampaignPolicy::class);

        Event::listen(SaleCompleted::class, RecalculateLoyaltyTierOnSale::class);
        Event::listen(SaleReturnApproved::class, RecalculateLoyaltyTierOnReturn::class);
    }
}
