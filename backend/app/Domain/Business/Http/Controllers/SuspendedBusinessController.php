<?php

namespace App\Domain\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The page a suspended business is redirected to.
 *
 * It renders `Pages/Suspended`, which is standalone rather than wrapped in
 * AuthenticatedLayout. That is the point: the layout builds a sidebar,
 * module list, currency switcher and notification poller from data the
 * account no longer has access to, and a locked-out user should not meet
 * a screen that depends on any of it.
 */
class SuspendedBusinessController extends Controller
{
    public function __invoke(Request $request): Response|HttpResponse
    {
        $business = $request->user()?->business;

        // Not reachable unless it applies. Otherwise `/suspended` is a
        // page anyone can visit to be told their working account is
        // suspended, which is alarming and false.
        if ($business === null || ! $business->isBlockedByPlatform()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Suspended', [
            'businessName' => $business->name,
            'supportEmail' => config('contact.email'),
            'supportPhone' => config('contact.phone'),
            'whatsappUrl' => 'https://wa.me/'.config('contact.whatsapp'),
        ]);
    }
}
