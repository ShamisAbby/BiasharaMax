<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Security\Models\BlockedIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlockedIpController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip', 'unique:blocked_ips,ip_address'],
            'reason' => ['nullable', 'string', 'max:500'],
            'is_permanent' => ['boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        BlockedIp::query()->create([...$validated, 'blocked_by' => $request->user()->id]);

        return back()->with('status', 'ip-blocked');
    }

    public function destroy(BlockedIp $blockedIp): RedirectResponse
    {
        $blockedIp->delete();

        return back()->with('status', 'ip-unblocked');
    }
}
