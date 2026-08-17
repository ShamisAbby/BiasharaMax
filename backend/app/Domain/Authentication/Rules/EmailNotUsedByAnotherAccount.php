<?php

namespace App\Domain\Authentication\Rules;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One email address, one account, across the whole platform.
 *
 * Tenant users and platform staff live in two tables, each with its own
 * unique index. Those indexes make an address unique *within* a table and
 * say nothing about the other, so before this rule the same address could
 * hold a vendor account and a platform admin account at once.
 *
 * Why that is worth preventing rather than tolerating: sign-in, password
 * reset and support all key off the email. With two accounts sharing one
 * address, `/login` and `/admin/login` accept the same email with
 * different passwords, a reset link fixes exactly one of them without
 * saying which, and a support request naming only an email is ambiguous.
 * None of that fails loudly — it produces a person who is certain their
 * password is right and an operator who can see it is wrong.
 *
 * The rule is deliberately not a database constraint. No engine can
 * enforce uniqueness across two tables without a trigger or a shared
 * identity table, and both are larger changes than this needs. That means
 * a direct `User::create()` bypassing validation can still create an
 * overlap; the three creation paths all run through FormRequests, and
 * anything new must too.
 */
class EmailNotUsedByAnotherAccount implements ValidationRule
{
    /**
     * @param  string|null  $ignoreUserId  Tenant user allowed to keep its own address (profile edits).
     * @param  string|null  $ignorePlatformUserId  Platform user allowed to keep its own address.
     */
    public function __construct(
        private readonly ?string $ignoreUserId = null,
        private readonly ?string $ignorePlatformUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Compared lower-cased rather than relying on the column
        // collation. Email columns are case-insensitive on MySQL and
        // MariaDB by design, but this rule should not quietly stop
        // working if that ever changes, or on any other engine. Signups
        // are rare enough that giving up the index here costs nothing.
        $email = mb_strtolower(trim($value));

        $takenByUser = User::query()
            ->when($this->ignoreUserId !== null, fn ($q) => $q->whereKeyNot($this->ignoreUserId))
            ->whereRaw('lower(email) = ?', [$email])
            ->exists();

        $takenByPlatformUser = PlatformUser::query()
            ->when($this->ignorePlatformUserId !== null, fn ($q) => $q->whereKeyNot($this->ignorePlatformUserId))
            ->whereRaw('lower(email) = ?', [$email])
            ->exists();

        if ($takenByUser || $takenByPlatformUser) {
            // One message for both cases, on purpose. Saying *which* kind
            // of account holds an address would tell anyone who can reach
            // the signup form whether a given email belongs to a platform
            // administrator, which is worth more to an attacker than it is
            // to the person signing up.
            $fail('This email address is already registered.');
        }
    }
}
