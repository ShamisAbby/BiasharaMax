<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Backup\Services\BackupService;
use App\Domain\Backup\Services\DatabaseSqlDumpService;
use App\Domain\Backup\Services\DatabaseSqlRestoreService;
use App\Domain\Backup\Services\RestoreService;
use App\Domain\Monitoring\Models\BackupRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(Request $request, BackupService $service): Response
    {
        $records = BackupRecord::query()->latest('started_at')->paginate(20)->withQueryString();

        return Inertia::render('Platform/System/Backup/Index', [
            'records' => [
                'data' => $records->items(),
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'total' => $records->total(),
                    'links' => $records->linkCollection()->toArray(),
                ],
            ],
            'files' => $service->listBackupFiles(),
        ]);
    }

    public function run(Request $request, BackupService $service): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([BackupRecord::TYPE_DATABASE, BackupRecord::TYPE_STORAGE, BackupRecord::TYPE_FULL])],
        ]);

        $record = $service->run($validated['type'], BackupRecord::TRIGGERED_MANUAL);

        return back()->with('status', $record->status === BackupRecord::STATUS_SUCCESS ? 'backup-completed' : 'backup-failed');
    }

    public function download(BackupRecord $backupRecord): StreamedResponse
    {
        abort_unless($backupRecord->file_path && Storage::disk($backupRecord->disk)->exists($backupRecord->file_path), 404);

        return Storage::disk($backupRecord->disk)->download($backupRecord->file_path);
    }

    public function destroy(BackupRecord $backupRecord, BackupService $service): RedirectResponse
    {
        $service->delete($backupRecord);

        return back()->with('status', 'backup-deleted');
    }

    public function preview(BackupRecord $backupRecord, RestoreService $service): JsonResponse
    {
        try {
            return response()->json($service->preview($backupRecord));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Destructive — overwrites current data with the backup's contents.
     * The frontend requires the SuperAdmin to type the backup's exact
     * filename to confirm before this request is ever sent.
     */
    public function restore(Request $request, BackupRecord $backupRecord, RestoreService $service): RedirectResponse
    {
        $validated = $request->validate(['confirmation' => ['required', 'string']]);

        $expectedFilename = basename($backupRecord->file_path ?? '');
        if ($validated['confirmation'] !== $expectedFilename) {
            return back()->withErrors(['confirmation' => 'Filename confirmation does not match.']);
        }

        try {
            $service->restore($backupRecord);
        } catch (\Throwable $e) {
            return back()->withErrors(['restore' => $e->getMessage()]);
        }

        return back()->with('status', 'restore-completed');
    }

    /**
     * Plain `.sql` export, alongside the zip archives above.
     *
     * Written in PHP rather than by `mysqldump`, because the client binary
     * is routinely missing from the web process's PATH on XAMPP and
     * Homebrew installs — which is why `backup:run` reports failures on
     * this project today. A `.sql` file is also what people actually ask
     * for when they want to move a database somewhere else.
     */
    public function exportSql(DatabaseSqlDumpService $dump): StreamedResponse
    {
        return response()->streamDownload(function () use ($dump): void {
            foreach ($dump->stream() as $chunk) {
                echo $chunk;
                flush();
            }
        }, $dump->filename(), [
            'Content-Type' => 'application/sql',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Step one of a `.sql` restore: report what the file would do.
     */
    public function inspectSql(Request $request, DatabaseSqlRestoreService $service): JsonResponse
    {
        $request->validate([
            // Not MIME-checked — see BusinessBackupController for why. The
            // format header is what determines whether this is our file.
            'backup' => ['required', 'file', 'max:1048576'],
        ]);

        $path = $request->file('backup')->store('platform-restore');

        try {
            return response()->json($service->inspect(Storage::path($path)) + ['token' => $path]);
        } catch (\Throwable $e) {
            Storage::delete($path);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Executes a previously inspected `.sql` file.
     *
     * The token is the storage path returned by `inspectSql`, so the file
     * being restored is provably the one that was inspected — re-uploading
     * between the two steps would otherwise let a different file through
     * the confirmation the admin just read.
     */
    public function restoreSql(Request $request, DatabaseSqlRestoreService $service): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'confirmation' => ['required', 'string'],
        ]);

        // Path traversal guard: the token comes back from the client, so it
        // is treated as untrusted input even though we generated it.
        if (! str_starts_with($validated['token'], 'platform-restore/') || str_contains($validated['token'], '..')) {
            return back()->withErrors(['restore' => 'That upload is no longer available. Please choose the file again.']);
        }

        if (! Storage::exists($validated['token'])) {
            return back()->withErrors(['restore' => 'That upload is no longer available. Please choose the file again.']);
        }

        if (trim($validated['confirmation']) !== 'RESTORE DATABASE') {
            return back()->withErrors(['confirmation' => 'Type RESTORE DATABASE exactly to confirm.']);
        }

        try {
            $service->restore(Storage::path($validated['token']));
        } catch (\Throwable $e) {
            return back()->withErrors(['restore' => $e->getMessage()]);
        } finally {
            Storage::delete($validated['token']);
        }

        return back()->with('status', 'restore-completed');
    }
}
