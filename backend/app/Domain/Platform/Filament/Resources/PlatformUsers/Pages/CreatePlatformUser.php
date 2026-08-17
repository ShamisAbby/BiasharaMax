<?php

namespace App\Domain\Platform\Filament\Resources\PlatformUsers\Pages;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Filament\Resources\PlatformUsers\PlatformUserResource;
use App\Domain\Platform\Services\PlatformAdminInvitationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * A "create" here is really an invite — PlatformAdminController::store()
 * calls PlatformAdminInvitationService::invite() rather than a raw model
 * create, so this override does the same instead of the generic
 * new PlatformUser($data) CreateRecord would otherwise do (which would
 * fail anyway — the form only submits name/email/platform_role_id, no
 * password, and the model requires one).
 */
class CreatePlatformUser extends CreateRecord
{
    protected static string $resource = PlatformUserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var PlatformUser $invitedBy */
        $invitedBy = Auth::guard('platform')->user();

        return app(PlatformAdminInvitationService::class)->invite($invitedBy, $data);
    }

    protected function afterCreate(): void
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
