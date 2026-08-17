<?php

namespace Database\Seeders;

use App\Domain\RBAC\Services\PlatformRoleProvisioningService;
use Illuminate\Database\Seeder;

class PlatformRoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PlatformRoleProvisioningService::class)->provisionDefaultRoles();
    }
}
