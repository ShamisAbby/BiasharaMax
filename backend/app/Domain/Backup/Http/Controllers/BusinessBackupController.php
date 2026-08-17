<?php

namespace App\Domain\Backup\Http\Controllers;

use App\Domain\Backup\Services\TenantSqlExportService;
use App\Domain\Backup\Services\TenantSqlImportService;
use App\Domain\Backup\Support\TenantTableMap;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backup & Restore for a single business.
 *
 * The platform's own backup screen dumps the whole database; this one
 * cannot and must not. A vendor gets a `.sql` file containing only their
 * records, and restoring one only ever writes into their own business —
 * see TenantSqlImportService for why the uploaded file is parsed rather
 * than executed.
 */
class BusinessBackupController extends Controller
{
    /**
     * How long a previewed upload waits for the owner to confirm it.
     *
     * Long enough to read the summary and think about it, short enough
     * that abandoned uploads of other people's business data don't sit on
     * disk. Cleared on both confirm and cancel; this is the backstop.
     */
    private const PENDING_TTL_MINUTES = 30;

    public function __construct(
        private readonly TenantSqlExportService $exporter,
        private readonly TenantSqlImportService $importer,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->hasPermission('backups.view'), 403);

        return Inertia::render('Settings/Backups', [
            'businessId' => $user->business?->getKey(),
            'canExport' => $user->hasPermission('backups.create'),
            'canRestore' => $user->hasPermission('backups.restore'),
            'businessName' => $user->business?->name,
            'tableCount' => count(TenantTableMap::allTables()),
            // Shown in the UI rather than buried in a doc: an owner about
            // to rely on this for disaster recovery needs to know upfront
            // that their staff accounts and subscription are not in it.
            'excluded' => TenantTableMap::EXCLUDED,
            'pending' => $this->pendingPreview($request),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user->hasPermission('backups.create'), 403);

        $business = $user->business;
        abort_unless($business !== null, 404);

        $filename = $this->exporter->filename($business);

        // Streamed, not built in memory — see TenantSqlExportService.
        return response()->streamDownload(function () use ($business): void {
            foreach ($this->exporter->stream($business) as $chunk) {
                echo $chunk;
                flush();
            }
        }, $filename, [
            'Content-Type' => 'application/sql',
            // Long exports must not be cut off by the proxy buffering them.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Step one of a restore: read the file and show what it contains.
     *
     * Split from the restore itself on purpose. Replacing every record a
     * business owns is not something anyone should trigger with a single
     * click on a file they picked from a folder.
     */
    public function preview(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasPermission('backups.restore'), 403);

        // Size-limited but not MIME-checked. A `.sql` file's detected type
        // varies by platform (text/plain, application/sql, application/x-sql,
        // application/octet-stream), so a MIME rule here rejects legitimate
        // backups on some machines and none of it is a security control
        // anyway — the format header check in the importer is what decides
        // whether this is really one of our files.
        $request->validate([
            'backup' => ['required', 'file', 'max:204800'],
        ], [], ['backup' => 'backup file']);

        $path = $request->file('backup')->store('backup-imports');

        try {
            $preview = $this->importer->preview(Storage::path($path));
        } catch (RuntimeException $e) {
            Storage::delete($path);

            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        $request->session()->put('business_backup_import', [
            'path' => $path,
            'name' => $request->file('backup')->getClientOriginalName(),
            'preview' => $preview,
            'expires_at' => now()->addMinutes(self::PENDING_TTL_MINUTES)->toIso8601String(),
        ]);

        return back()->with('status', 'backup-inspected');
    }

    public function restore(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasPermission('backups.restore'), 403);

        $business = $user->business;
        abort_unless($business !== null, 404);

        $pending = $request->session()->get('business_backup_import');

        if (! is_array($pending) || ! Storage::exists($pending['path'] ?? '')) {
            return back()->withErrors([
                'backup' => 'That upload is no longer available. Please choose the file again.',
            ]);
        }

        // Typing the business name is the confirmation. A checkbox or a
        // second button is too easy to click through for something that
        // deletes every record the business owns.
        $request->validate(['confirmation' => ['required', 'string']]);

        if (trim($request->string('confirmation')->value()) !== trim((string) $business->name)) {
            return back()->withErrors([
                'confirmation' => 'Type the business name exactly to confirm the restore.',
            ]);
        }

        try {
            $result = $this->importer->restore($business, Storage::path($pending['path']));
        } catch (\Throwable $e) {
            return back()->withErrors([
                'backup' => 'The restore failed and nothing was changed: '.$e->getMessage(),
            ]);
        } finally {
            Storage::delete($pending['path']);
            $request->session()->forget('business_backup_import');
        }

        return back()->with('success', sprintf(
            'Restored %s records across %d tables.',
            number_format(array_sum($result['restored'])),
            count($result['restored']),
        ));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('business_backup_import');

        if (is_array($pending) && isset($pending['path'])) {
            Storage::delete($pending['path']);
        }

        return back()->with('status', 'backup-discarded');
    }

    /**
     * The pending upload, if there is one and it hasn't gone stale.
     *
     * @return array<string, mixed>|null
     */
    private function pendingPreview(Request $request): ?array
    {
        $pending = $request->session()->get('business_backup_import');

        if (! is_array($pending)) {
            return null;
        }

        $expired = isset($pending['expires_at']) && now()->isAfter($pending['expires_at']);

        if ($expired || ! Storage::exists($pending['path'] ?? '')) {
            if (isset($pending['path'])) {
                Storage::delete($pending['path']);
            }

            $request->session()->forget('business_backup_import');

            return null;
        }

        return [
            'name' => $pending['name'] ?? 'backup.sql',
            'preview' => $pending['preview'] ?? null,
        ];
    }
}
