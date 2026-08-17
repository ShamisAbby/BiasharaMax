<?php

namespace App\Domain\Authentication\Support;

/**
 * One definition of the identity-field rules shared by tenant `users`
 * and platform staff, so the Filament forms, request validation and any
 * future API all enforce the same thing rather than drifting apart.
 *
 * Username: unique, at most 15 characters, letters/digits/underscore
 * only — no spaces and no other punctuation. The pattern is anchored so
 * a value like `bad name!` can't pass by matching a substring.
 */
final class UserIdentityRules
{
    public const USERNAME_MAX_LENGTH = 15;

    public const USERNAME_REGEX = '/^[A-Za-z0-9_]+$/';

    public const USERNAME_MESSAGE = 'The username may only contain letters, numbers and underscores — no spaces or other symbols.';

    public const PHONE_MAX_LENGTH = 32;

    /**
     * Rules common to both user tables. Uniqueness is deliberately NOT
     * included: it depends on which table is being written and which
     * record (if any) to ignore, so each caller adds its own
     * `unique`/`ignoreRecord` on top.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'username' => ['string', 'max:'.self::USERNAME_MAX_LENGTH, 'regex:'.self::USERNAME_REGEX],
            'email' => ['string', 'email:rfc,dns', 'max:255'],
            'phone' => ['string', 'max:'.self::PHONE_MAX_LENGTH],
        ];
    }

    /**
     * Which column a sign-in identifier should be matched against.
     *
     * Sign-in accepts either an email address or a username in the same
     * box, and the two can never be confused: USERNAME_REGEX excludes
     * `@`, so any identifier containing one cannot be a valid username.
     * That makes the `@` test exact rather than a heuristic — a value
     * with an `@` is either a real email or matches nothing at all.
     */
    public static function loginColumn(string $identifier): string
    {
        return str_contains($identifier, '@') ? 'email' : 'username';
    }
}
