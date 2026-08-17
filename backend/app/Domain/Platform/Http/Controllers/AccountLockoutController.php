<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Security\Models\AccountLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountLockoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lockable_type' => ['required', Rule::in([AccountLockout::TYPE_PLATFORM_USER, AccountLockout::TYPE_USER])],
            'lockable_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
        ]);

        AccountLockout::query()->create([...$validated, 'locked_at' => now()]);

        return back()->with('status', 'account-locked');
    }

    public function unlock(Request $request, AccountLockout $accountLockout): RedirectResponse
    {
        $accountLockout->update(['unlocked_at' => now(), 'unlocked_by' => $request->user()->id]);

        return back()->with('status', 'account-unlocked');
    }
}
