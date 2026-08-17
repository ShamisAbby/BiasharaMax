<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Http\Requests\RoleTemplateRequest;
use App\Domain\Platform\Http\Resources\PermissionResource;
use App\Domain\Platform\Http\Resources\RoleTemplateResource;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\RoleTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = RoleTemplate::query()
            ->withCount('permissions')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('action')
            ->get();

        return Inertia::render('Platform/Rbac/RoleTemplates/Index', [
            'roleTemplates' => RoleTemplateResource::collection($templates),
            'permissions' => PermissionResource::collection($permissions),
        ]);
    }

    public function store(RoleTemplateRequest $request): RedirectResponse
    {
        $template = RoleTemplate::query()->create([
            ...$request->safe()->except('permission_ids'),
            'created_by' => $request->user()->id,
        ]);
        $template->permissions()->sync($request->input('permission_ids', []));

        return back()->with('status', 'role-template-created');
    }

    public function update(RoleTemplateRequest $request, RoleTemplate $roleTemplate): RedirectResponse
    {
        $roleTemplate->update($request->safe()->except('permission_ids'));

        if ($request->has('permission_ids')) {
            $roleTemplate->permissions()->sync($request->input('permission_ids', []));
        }

        return back()->with('status', 'role-template-updated');
    }

    public function destroy(RoleTemplate $roleTemplate): RedirectResponse
    {
        if ($roleTemplate->is_system) {
            return back()->withErrors(['role_template' => 'System templates cannot be deleted.']);
        }

        $roleTemplate->delete();

        return back()->with('status', 'role-template-deleted');
    }
}
