<?php

namespace App\Domain\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Authentication\Http\Requests\AcceptInvitationRequest;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Services\EmployeeInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function __construct(
        private readonly EmployeeInvitationService $invitationService,
    ) {}

    /**
     * The route itself is protected by Laravel's `signed` middleware, so an
     * invalid or expired link never reaches this action.
     */
    public function show(Request $request, User $user): Response|RedirectResponse
    {
        if ($user->status !== User::STATUS_INVITED) {
            return redirect()->route('login')->with('status', 'invitation-already-accepted');
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'employeeName' => $user->name,
            'businessName' => $user->business?->name,
        ]);
    }

    public function store(AcceptInvitationRequest $request, User $user): RedirectResponse
    {
        if ($user->status !== User::STATUS_INVITED) {
            return redirect()->route('login');
        }

        $this->invitationService->activate($user, $request->validated('password'));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
