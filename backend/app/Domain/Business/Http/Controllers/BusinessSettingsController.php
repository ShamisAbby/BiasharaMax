<?php

namespace App\Domain\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Http\Requests\BusinessSettingsUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->authorize('view', $request->user()->business);

        return Inertia::render('Settings/Business', [
            'business' => $request->user()->business,
        ]);
    }

    public function update(BusinessSettingsUpdateRequest $request): RedirectResponse
    {
        $request->user()->business->update($request->validated());

        return back()->with('status', 'business-updated');
    }
}
