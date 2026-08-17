<?php

/**
 * Download details for the BiasharaMax Desktop till, shown on the public
 * landing page.
 *
 * Everything here is driven by env rather than hardcoded, so publishing a
 * new build is a deploy-time change and not a code change. Nothing needs
 * to be filled in for the page to work: a platform with no `url` renders
 * as "Coming soon" and cannot be clicked, which is the honest state until
 * signed installers actually exist.
 *
 * `size` and `checksum` are optional and only render when present. The
 * checksum is worth publishing once builds are real — this is a till that
 * handles money, and a visible SHA-256 is the only way someone can tell a
 * genuine installer from one a network intercepted.
 */
return [

    'version' => env('DESKTOP_APP_VERSION'),

    /**
     * ISO date, e.g. 2026-08-11. Rendered as "Updated 11 Aug 2026".
     */
    'released_at' => env('DESKTOP_APP_RELEASED_AT'),

    /**
     * Where "Release notes" points. Omit to hide the link.
     */
    'release_notes_url' => env('DESKTOP_APP_RELEASE_NOTES_URL'),

    'platforms' => [

        [
            'key' => 'windows',
            'name' => 'Windows',
            'requirement' => 'Windows 10 or later · 64-bit',
            'format' => '.exe installer',
            'url' => env('DESKTOP_APP_URL_WINDOWS'),
            'size' => env('DESKTOP_APP_SIZE_WINDOWS'),
            'checksum' => env('DESKTOP_APP_SHA256_WINDOWS'),
        ],

        [
            'key' => 'macos',
            'name' => 'macOS',
            'requirement' => 'macOS 11 or later · Apple silicon & Intel',
            'format' => '.dmg disk image',
            'url' => env('DESKTOP_APP_URL_MACOS'),
            'size' => env('DESKTOP_APP_SIZE_MACOS'),
            'checksum' => env('DESKTOP_APP_SHA256_MACOS'),
        ],

        [
            'key' => 'linux',
            'name' => 'Linux',
            'requirement' => 'Ubuntu 20.04+ / Debian 11+ · 64-bit',
            'format' => '.AppImage',
            'url' => env('DESKTOP_APP_URL_LINUX'),
            'size' => env('DESKTOP_APP_SIZE_LINUX'),
            'checksum' => env('DESKTOP_APP_SHA256_LINUX'),
        ],

    ],

];
