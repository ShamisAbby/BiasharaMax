<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Support\Models\PlatformAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformAnnouncementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience' => ['required', 'string', 'max:20'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        PlatformAnnouncement::query()->create([...$validated, 'created_by' => $request->user()->id]);

        return back()->with('status', 'announcement-created');
    }

    public function update(Request $request, PlatformAnnouncement $platformAnnouncement): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $platformAnnouncement->update($validated);

        return back()->with('status', 'announcement-updated');
    }

    public function destroy(PlatformAnnouncement $platformAnnouncement): RedirectResponse
    {
        $platformAnnouncement->delete();

        return back()->with('status', 'announcement-deleted');
    }
}
