<?php

namespace App\Domain\Platform\Filament\Resources\PlatformUsers\Pages;

use App\Domain\Platform\Filament\Resources\PlatformUsers\PlatformUserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformUser extends EditRecord
{
    protected static string $resource = PlatformUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncLegacyRoleColumn();
    }

    /**
     * Filament saves a `multiple()` relationship select AFTER the record
     * itself, so the legacy `platform_role_id` column can't be set during
     * creation/update — it is mirrored here once the pivot exists. The
     * column no longer drives authorization, but several screens still
     * display a role name from it, and it is the rollback path if the
     * pivot migration has to be undone.
     *
     * saveQuietly() because the model's `saved` hook syncs the pivot FROM
     * this column; writing it normally here would re-sync the pivot down
     * to just this one role and silently discard the others.
     */
    protected function syncLegacyRoleColumn(): void
    {
        $record = $this->getRecord();

        $record->platform_role_id = $record->platformRoles()->value('platform_roles.id');
        $record->saveQuietly();
    }
}
