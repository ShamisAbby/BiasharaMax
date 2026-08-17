<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Support\Models\SupportAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportAgentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform_user_id' => ['required', 'uuid', 'exists:platform_users,id', 'unique:support_agents,platform_user_id'],
            'support_department_id' => ['nullable', 'uuid', 'exists:support_departments,id'],
            'max_concurrent_tickets' => ['nullable', 'integer', 'min:1'],
        ]);

        SupportAgent::query()->create($validated);

        return back()->with('status', 'agent-created');
    }

    public function update(Request $request, SupportAgent $supportAgent): RedirectResponse
    {
        $validated = $request->validate([
            'support_department_id' => ['nullable', 'uuid', 'exists:support_departments,id'],
            'is_active' => ['boolean'],
            'max_concurrent_tickets' => ['nullable', 'integer', 'min:1'],
        ]);

        $supportAgent->update($validated);

        return back()->with('status', 'agent-updated');
    }

    public function destroy(SupportAgent $supportAgent): RedirectResponse
    {
        $supportAgent->delete();

        return back()->with('status', 'agent-removed');
    }
}
