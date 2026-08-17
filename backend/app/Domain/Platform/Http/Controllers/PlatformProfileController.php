<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Http\Requests\PlatformProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PlatformProfileController extends Controller
{
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user('platform');

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('status', 'avatar-updated');
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Platform/Profile/Edit', [
            'status' => session('status'),
        ]);
    }

    public function update(PlatformProfileUpdateRequest $request): RedirectResponse
    {
        $request->user('platform')->fill($request->validated());

        if ($request->user('platform')->isDirty('email')) {
            $request->user('platform')->email_verified_at = null;
        }

        $request->user('platform')->save();

        return back()->with('status', 'profile-updated');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:platform'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user('platform')->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
