<?php

namespace App\Domain\Platform\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Http\Requests\AcceptPlatformInvitationRequest;
use App\Domain\Platform\Services\PlatformAdminInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AcceptPlatformInvitationController extends Controller
{
    public function __construct(
        private readonly PlatformAdminInvitationService $invitationService,
    ) {}

    /**
     * The route itself is protected by Laravel's `signed` middleware, so an
     * invalid or expired link never reaches this action.
     */
    public function show(Request $request, PlatformUser $platformUser): Response|RedirectResponse
    {
        if ($platformUser->status !== PlatformUser::STATUS_INVITED) {
            return redirect()->route('platform.login')->with('status', 'invitation-already-accepted');
        }

        return Inertia::render('Platform/Auth/AcceptInvitation', [
            'adminName' => $platformUser->name,
        ]);
    }

    public function store(AcceptPlatformInvitationRequest $request, PlatformUser $platformUser): RedirectResponse
    {
        if ($platformUser->status !== PlatformUser::STATUS_INVITED) {
            return redirect()->route('platform.login');
        }

        $this->invitationService->activate($platformUser, $request->validated('password'));

        Auth::guard('platform')->login($platformUser);

        return redirect(route('platform.dashboard', absolute: false));
    }
}
