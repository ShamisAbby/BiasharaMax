<?php

namespace App\Domain\RBAC\Services;

use App\Domain\RBAC\Models\PlatformRole;
use App\Domain\RBAC\Models\Role;
use App\Domain\RBAC\Models\RoleTemplate;
use Illuminate\Support\Str;

class RoleTemplateService
{
    public function applyToRole(Role $role, RoleTemplate $template): Role
    {
        $role->permissions()->sync($template->permissions()->pluck('permissions.id'));

        return $role->refresh();
    }

    public function applyToPlatformRole(PlatformRole $role, RoleTemplate $template): PlatformRole
    {
        $role->permissions()->sync($template->permissions()->pluck('permissions.id'));

        return $role->refresh();
    }

    public function cloneRole(Role $role, string $newName): Role
    {
        $clone = Role::query()->create([
            'business_id' => $role->business_id,
            'name' => $newName,
            'slug' => $this->uniqueSlug($newName),
            'is_system' => false,
            'description' => $role->description,
        ]);

        $clone->permissions()->sync($role->permissions()->pluck('permissions.id'));

        return $clone;
    }

    public function clonePlatformRole(PlatformRole $role, string $newName): PlatformRole
    {
        $clone = PlatformRole::query()->create([
            'name' => $newName,
            'slug' => $this->uniqueSlug($newName),
            'is_system' => false,
            'description' => $role->description,
        ]);

        $clone->permissions()->sync($role->permissions()->pluck('permissions.id'));

        return $clone;
    }

    private function uniqueSlug(string $name): string
    {
        return Str::slug($name).'-'.Str::lower(Str::random(4));
    }
}
