<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Self-service API tokens via Sanctum, already installed on PlatformUser
 * (`HasApiTokens`) but unused until now — no new table needed.
 */
class ApiTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
        ]);

        $token = $request->user()->createToken($validated['name'], $validated['abilities'] ?? ['*']);

        return back()->with(['status' => 'token-created', 'plain_text_token' => $token->plainTextToken]);
    }

    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back()->with('status', 'token-revoked');
    }
}
