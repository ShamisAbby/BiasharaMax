<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Http\Resources\PermissionResource;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\PlatformRole;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only — a search/browse view over every permission in the system
 * (tenant + platform scope), grouped by module, plus how many platform
 * roles currently grant each one. Per-business tenant role assignment
 * is managed on the existing tenant RoleController.
 */
class PermissionMatrixController extends Controller
{
    public function index(Request $request): Response
    {
        $permissions = Permission::query()
            ->when($request->filled('scope'), fn ($query) => $query->where('scope', $request->string('scope')))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->string('module')))
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                // Plain `like` is case-sensitive on Postgres (MySQL's default
                // collation happens to be case-insensitive, which papered
                // over this until a collation override or a non-default
                // engine exposed it — see PermissionMatrixTest > matrix can
                // be searched). Wrapping both sides in LOWER() makes the
                // match explicitly case-insensitive on every engine, rather
                // than depending on collation defaults.
                $search = Str::lower($request->string('search')->trim()->value());
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(slug) like ?', ["%{$search}%"]);
                });
            })
            ->orderBy('module')
            ->orderBy('action')
            ->get();

        $platformRoles = PlatformRole::query()->with('permissions:id')->get();

        return Inertia::render('Platform/Rbac/PermissionMatrix/Index', [
            'permissions' => PermissionResource::collection($permissions),
            'platformRoles' => $platformRoles->map(fn (PlatformRole $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permission_ids' => $role->permissions->pluck('id'),
            ]),
            'modules' => Permission::query()->distinct()->orderBy('module')->pluck('module'),
            'filters' => $request->only(['search', 'scope', 'module']),
        ]);
    }
}
