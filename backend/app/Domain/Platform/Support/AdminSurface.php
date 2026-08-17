<?php

namespace App\Domain\Platform\Support;

/**
 * The two administrator surfaces this platform ships.
 *
 * These are two applications, not two themes — an Inertia/React SPA and
 * a Filament/Livewire panel — sharing the `platform` auth guard and
 * nothing else. That sharing is the whole reason switching is cheap: an
 * admin signed into one is already signed into the other, so a switch is
 * a redirect rather than a re-authentication.
 *
 * Naming this "surface" rather than "theme" throughout is deliberate.
 * A theme promises the same features drawn differently; these two do not
 * have the same features, and [self::ONLY_ON] exists precisely because
 * that promise would be false. Anything user-facing should say which
 * screens live where rather than imply parity.
 */
final class AdminSurface
{
    /** The Inertia/React admin at /admin. */
    public const INERTIA = 'admin';

    /** The Filament/Livewire panel at /platform. */
    public const FILAMENT = 'platform';

    /**
     * The default for an administrator who has never chosen.
     *
     * The Inertia admin, because it is the more complete of the two —
     * every screen the Filament panel has, plus the ones listed below.
     * A default should never be the surface that is missing things.
     */
    public const DEFAULT = self::INERTIA;

    /**
     * Screens that exist on exactly one surface.
     *
     * Kept here so the switcher can tell an admin what they are leaving
     * behind rather than letting them discover it as an absence. Every
     * entry is a genuine gap, not a styling difference.
     *
     * Two of the three are matrices rather than CRUD, which is why they
     * have no Filament equivalent — that is the shape Filament is worst
     * at, so these are unlikely to be ported cheaply.
     *
     * @var array<string, list<string>>
     */
    public const ONLY_ON = [
        self::INERTIA => [
            'Business module toggles',
            'RBAC permission matrix',
            'Role templates',
            'Operations dashboard',
        ],
        self::FILAMENT => [],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::INERTIA, self::FILAMENT];
    }

    public static function isValid(?string $surface): bool
    {
        return in_array($surface, self::all(), true);
    }

    /**
     * Normalises anything unrecognised — including null — to the
     * default, so a stale or hand-edited database value can never leave
     * an admin with nowhere to land.
     */
    public static function normalise(?string $surface): string
    {
        return self::isValid($surface) ? $surface : self::DEFAULT;
    }

    public static function label(string $surface): string
    {
        return match (self::normalise($surface)) {
            self::FILAMENT => 'Filament panel',
            default => 'Classic admin',
        };
    }

    public static function path(string $surface): string
    {
        return match (self::normalise($surface)) {
            self::FILAMENT => '/platform',
            default => '/admin',
        };
    }

    /**
     * The surface a request belongs to, from its path.
     *
     * Used by the switcher to know which way it is pointing without
     * either surface having to tell it.
     */
    public static function fromPath(string $path): string
    {
        return str_starts_with(ltrim($path, '/'), 'platform')
            ? self::FILAMENT
            : self::INERTIA;
    }

    /**
     * @return list<string>
     */
    public static function missingFrom(string $surface): array
    {
        $other = self::normalise($surface) === self::INERTIA ? self::FILAMENT : self::INERTIA;

        return self::ONLY_ON[$other] ?? [];
    }
}
