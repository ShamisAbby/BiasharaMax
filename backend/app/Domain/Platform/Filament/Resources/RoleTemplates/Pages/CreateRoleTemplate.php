<?php

namespace App\Domain\Platform\Filament\Resources\RoleTemplates\Pages;

use App\Domain\Platform\Filament\Resources\RoleTemplates\RoleTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateRoleTemplate extends CreateRecord
{
    protected static string $resource = RoleTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::guard('platform')->id();

        return $data;
    }
}
