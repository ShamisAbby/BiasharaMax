<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Services\EnvEditorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnvSettingsController extends Controller
{
    /** Env keys allowed per logical group — nothing outside these lists is ever written. */
    private const ALLOWED = [
        'app' => [
            'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL',
        ],
        'mail' => [
            'MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT',
            'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
        ],
        'database' => [
            'DB_CONNECTION', 'DB_HOST', 'DB_PORT',
            'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        ],
    ];

    public function update(Request $request, string $group, EnvEditorService $env): RedirectResponse
    {
        if (! array_key_exists($group, self::ALLOWED)) {
            abort(404);
        }

        $prefix  = strtoupper($group === 'database' ? 'DB' : $group) . '_';
        $allowed = self::ALLOWED[$group];
        $values  = [];

        foreach ($request->except('_token') as $key => $value) {
            $envKey = $prefix . strtoupper($key);

            if (! in_array($envKey, $allowed, true)) {
                continue;
            }

            // Normalise booleans submitted as checkbox strings
            if (is_bool($value) || in_array($value, ['true', 'false', '1', '0', 'on', 'off'], true)) {
                $values[$envKey] = in_array($value, [true, 'true', '1', 'on'], true) ? 'true' : 'false';
            } else {
                $values[$envKey] = (string) ($value ?? '');
            }
        }

        $env->set($values);

        return back()->with('status', 'settings-updated');
    }
}
