<?php

namespace App\Domain\Platform\Filament\Concerns;

use Filament\Forms\Components\BaseFileUpload;

/**
 * Makes an existing upload's preview URL root-relative.
 *
 * Filament builds previews with `Storage::disk(...)->url($file)`, and the
 * `public` disk's URL is derived from APP_URL (config/filesystems.php).
 * Whenever the browser's host differs from APP_URL — `127.0.0.1:8000` vs
 * `localhost:8000` is the usual one in local dev — that produces a
 * cross-origin URL. A plain <img> would still render it, but FilePond
 * loads previews over XHR, so the request is blocked by CORS and the
 * component sits on its loading spinner forever with no console error
 * that points at the cause.
 *
 * Rewriting to just the path keeps the request same-origin whatever host
 * the panel is reached on. Only applied to local-driver disks: on a
 * remote driver (S3 and friends) the host is load-bearing and stripping
 * it would break the URL entirely.
 */
trait ResolvesUploadPreviewsSameOrigin
{
    protected function resolveUploadPreviewsSameOrigin(BaseFileUpload $upload): BaseFileUpload
    {
        return $upload->getUploadedFileUsing(
            static function (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                $data = $component->getUploadedFile($file, $storedFileNames);

                if ($data === null) {
                    return null;
                }

                $driver = config("filesystems.disks.{$component->getDiskName()}.driver");

                if ($driver !== 'local') {
                    return $data;
                }

                $path = parse_url((string) $data['url'], PHP_URL_PATH);

                if (is_string($path) && $path !== '') {
                    $data['url'] = $path;
                }

                return $data;
            },
        );
    }
}
