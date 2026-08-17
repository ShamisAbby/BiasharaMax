<?php

namespace App\Domain\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Http\Requests\BusinessRegistrationRequest;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Business\Services\BusinessRegistrationService;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BusinessRegistrationController extends Controller
{
    public function __construct(
        private readonly BusinessRegistrationService $registrationService,
    ) {}

    /**
     * Display the combined business + owner registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'price_quarterly', 'price_yearly', 'trial_days', 'features']),
            'businessTypes' => BusinessType::query()
                ->where('status', BusinessType::STATUS_ACTIVE)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function createWithCode(): Response
    {
        return Inertia::render('Auth/RegisterWithCode', [
            'businessTypes' => BusinessType::query()
                ->where('status', BusinessType::STATUS_ACTIVE)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Handle the combined business + owner registration request.
     */
    public function store(BusinessRegistrationRequest $request): RedirectResponse
    {
        $owner = $this->registrationService->register($request->validated());

        Auth::login($owner);

        return redirect(route('dashboard', absolute: false));
    }
}
