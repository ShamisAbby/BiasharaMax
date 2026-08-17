<?php

namespace App\Domain\RBAC\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\RBAC\Http\Requests\RoleStoreRequest;
use App\Domain\RBAC\Http\Requests\RoleUpdateRequest;
use App\Domain\RBAC\Http\Resources\PermissionResource;
use App\Domain\RBAC\Http\Resources\RoleResource;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\Role;
use App\Domain\RBAC\Models\RoleTemplate;
use App\Domain\RBAC\Services\RoleTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->where('business_id', $request->user()->business_id)
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $permissionsByModule = Permission::query()
            ->where('scope', Permission::SCOPE_TENANT)
            ->orderBy('module')
            ->get()
            ->groupBy('module')
            ->map(fn ($permissions) => PermissionResource::collection($permissions));

        return Inertia::render('Settings/Roles', [
            'roles' => RoleResource::collection($roles),
            'permissions' => $permissionsByModule,
            'templates' => RoleTemplate::query()->where('scope', 'tenant')->get(['id', 'name']),
        ]);
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'business_id' => $request->user()->business_id,
            'name' => $request->validated('name'),
            'slug' => Str::slug($request->validated('name')),
            'description' => $request->validated('description'),
            'is_system' => false,
        ]);

        $role->permissions()->sync($request->validated('permissions', []));

        return back()->with('status', 'role-created');
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $role->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
        ]);

        $role->permissions()->sync($request->validated('permissions', []));

        return back()->with('status', 'role-updated');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $role->delete();

        return back()->with('status', 'role-deleted');
    }

    public function clone(Request $request, Role $role, RoleTemplateService $service): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->where('business_id', $request->user()->business_id)],
        ]);

        $service->cloneRole($role, $validated['name']);

        return back()->with('status', 'role-cloned');
    }

    public function applyTemplate(Request $request, Role $role, RoleTemplateService $service): RedirectResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validate(['role_template_id' => ['required', 'uuid', 'exists:role_templates,id']]);

        $template = RoleTemplate::query()->where('scope', 'tenant')->findOrFail($validated['role_template_id']);
        $service->applyToRole($role, $template);

        return back()->with('status', 'template-applied');
    }
}
