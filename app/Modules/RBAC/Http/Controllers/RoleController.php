<?php

namespace App\Modules\RBAC\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RBAC\Http\Requests\RoleStoreRequest;
use App\Modules\RBAC\Http\Requests\RoleUpdateRequest;
use App\Modules\RBAC\Http\Resources\PermissionResource;
use App\Modules\RBAC\Http\Resources\RoleResource;
use App\Modules\RBAC\Models\Permission;
use App\Modules\RBAC\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            ->orderBy('module')
            ->get()
            ->groupBy('module')
            ->map(fn ($permissions) => PermissionResource::collection($permissions));

        return Inertia::render('Settings/Roles', [
            'roles' => RoleResource::collection($roles),
            'permissions' => $permissionsByModule,
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
}
