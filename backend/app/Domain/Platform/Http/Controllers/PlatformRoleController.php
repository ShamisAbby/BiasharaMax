<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Http\Requests\PlatformRoleRequest;
use App\Domain\Platform\Http\Resources\PermissionResource;
use App\Domain\Platform\Http\Resources\PlatformRoleResource;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\PlatformRole;
use App\Domain\RBAC\Models\RoleTemplate;
use App\Domain\RBAC\Services\RoleTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = PlatformRole::query()
            ->withCount(['permissions', 'platformUsers'])
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $permissions = Permission::query()
            ->where('scope', Permission::SCOPE_PLATFORM)
            ->orderBy('module')
            ->orderBy('action')
            ->get();

        return Inertia::render('Platform/Rbac/PlatformRoles/Index', [
            'platformRoles' => PlatformRoleResource::collection($roles),
            'templates' => RoleTemplate::query()->where('scope', 'platform')->get(['id', 'name']),
            'permissions' => PermissionResource::collection($permissions),
        ]);
    }

    public function store(PlatformRoleRequest $request): RedirectResponse
    {
        $role = PlatformRole::query()->create($request->safe()->except('permission_ids'));
        $role->permissions()->sync($request->input('permission_ids', []));

        return back()->with('status', 'platform-role-created');
    }

    public function update(PlatformRoleRequest $request, PlatformRole $platformRole): RedirectResponse
    {
        $platformRole->update($request->safe()->except('permission_ids'));

        if ($request->has('permission_ids')) {
            $platformRole->permissions()->sync($request->input('permission_ids', []));
        }

        return back()->with('status', 'platform-role-updated');
    }

    public function destroy(PlatformRole $platformRole): RedirectResponse
    {
        if ($platformRole->is_system) {
            return back()->withErrors(['platform_role' => 'System roles cannot be deleted.']);
        }

        if ($platformRole->platformUsers()->exists()) {
            return back()->withErrors(['platform_role' => 'This role is assigned to platform users and cannot be deleted.']);
        }

        $platformRole->delete();

        return back()->with('status', 'platform-role-deleted');
    }

    public function clone(Request $request, PlatformRole $platformRole, RoleTemplateService $service): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $service->clonePlatformRole($platformRole, $validated['name']);

        return back()->with('status', 'platform-role-cloned');
    }

    public function applyTemplate(Request $request, PlatformRole $platformRole, RoleTemplateService $service): RedirectResponse
    {
        $validated = $request->validate(['role_template_id' => ['required', 'uuid', 'exists:role_templates,id']]);

        $template = RoleTemplate::query()->findOrFail($validated['role_template_id']);
        $service->applyToPlatformRole($platformRole, $template);

        return back()->with('status', 'template-applied');
    }
}
