<?php

namespace App\Domain\Finance\Support;

use Illuminate\Http\Request;

/**
 * Verifies that a webhook really came from Snippe.
 *
 * Snippe signs as `hex(HMAC-SHA256(secret, "{timestamp}.{raw_body}"))`, in
 * `X-Webhook-Signature` with the timestamp in `X-Webhook-Timestamp`.
 *
 * ---------------------------------------------------------------------
 * Deliberately stricter than the version this was ported from.
 *
 * The Tripfy implementation accepts EITHER `{timestamp}.{body}` or the
 * bare `{body}` as the signed message. That second form is the problem: a
 * signature over the body alone is valid forever, so anyone who observes
 * one completed-payment webhook can replay it indefinitely and be believed
 * every time. On this platform that would mean granting a paid
 * subscription, repeatedly, for free.
 *
 * So: the timestamp is required, it is part of the signed message, and
 * anything older than five minutes is refused. That is what the Snippe
 * documentation specifies, and it costs nothing to hold to it.
 *
 * If Snippe ever sends a body-only signature this will reject it — loudly,
 * at the point of failure, which is the right way round for money.
 * ---------------------------------------------------------------------
 */
class SnippeSignatureVerifier
{
    /**
     * How stale a webhook may be before it is treated as a replay.
     *
     * Five minutes is Snippe's own recommendation. Wide enough to absorb
     * clock drift between two servers, narrow enough that a captured
     * request is worthless by the time anyone gets around to reusing it.
     */
    public const MAX_AGE_SECONDS = 300;

    public static function isValid(Request $request, string $rawBody, string $secret): bool
    {
        if ($secret === '') {
            // No secret configured means nothing can be verified. Refusing
            // is the only safe answer: an unverified webhook that activates
            // a subscription is a free-subscription endpoint for anyone who
            // knows the URL.
            return false;
        }

        $signature = (string) $request->header('X-Webhook-Signature', '');
        $timestamp = (string) $request->header('X-Webhook-Timestamp', '');

        if ($signature === '' || $timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        // `hash_equals`, never `===`. String comparison short-circuits on
        // the first differing byte, which leaks how much of a guess was
        // right and lets a signature be recovered a byte at a time.
        return hash_equals($expected, $signature);
    }
}
