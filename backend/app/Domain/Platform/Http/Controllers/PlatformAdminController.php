<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Http\Requests\PlatformAdminInviteRequest;
use App\Domain\Platform\Http\Requests\PlatformAdminUpdateRequest;
use App\Domain\Platform\Http\Resources\PlatformAdminResource;
use App\Domain\Platform\Services\PlatformAdminInvitationService;
use App\Domain\RBAC\Models\PlatformRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $admins = PlatformUser::query()
            ->with('platformRole')
            ->orderBy('name')
            ->get();

        return Inertia::render('Platform/Staff/Index', [
            'admins' => PlatformAdminResource::collection($admins),
            'platformRoles' => PlatformRole::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(PlatformAdminInviteRequest $request, PlatformAdminInvitationService $service): RedirectResponse
    {
        $service->invite($request->user(), $request->validated());

        return back()->with('status', 'platform-admin-invited');
    }

    public function update(PlatformAdminUpdateRequest $request, PlatformUser $platformUser): RedirectResponse
    {
        $platformUser->update($request->validated());

        return back()->with('status', 'platform-admin-updated');
    }

    public function deactivate(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        if ($platformUser->is($request->user())) {
            return back()->withErrors(['platform_user' => 'You cannot deactivate your own account.']);
        }

        $platformUser->update(['status' => PlatformUser::STATUS_SUSPENDED]);

        return back()->with('status', 'platform-admin-deactivated');
    }

    public function activate(PlatformUser $platformUser): RedirectResponse
    {
        $platformUser->update(['status' => PlatformUser::STATUS_ACTIVE]);

        return back()->with('status', 'platform-admin-activated');
    }

    public function destroy(Request $request, PlatformUser $platformUser): RedirectResponse
    {
        if ($platformUser->is($request->user())) {
            return back()->withErrors(['platform_user' => 'You cannot remove your own account.']);
        }

        $platformUser->delete();

        return back()->with('status', 'platform-admin-removed');
    }
}
