<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Security\Models\SecurityAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SecurityAlertController extends Controller
{
    public function resolve(Request $request, SecurityAlert $securityAlert): RedirectResponse
    {
        $securityAlert->update([
            'is_resolved' => true,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back()->with('status', 'alert-resolved');
    }
}
