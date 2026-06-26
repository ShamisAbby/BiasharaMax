<?php

namespace App\Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Business\Http\Requests\BusinessRegistrationRequest;
use App\Modules\Business\Services\BusinessRegistrationService;
use App\Modules\Subscription\Models\SubscriptionPlan;
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
