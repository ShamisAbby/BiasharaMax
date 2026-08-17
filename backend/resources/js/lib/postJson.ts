/**
 * POST to a JSON endpoint without going through Inertia's router.
 *
 * Exists because reaching for `router.post()` here is the obvious move
 * and the wrong one. Inertia's router requires the response to be an
 * Inertia payload; a controller that answers with `response()->json()`
 * causes it to reject the reply and, in development, render the raw JSON
 * inside a full-screen error overlay. It looks like the app has crashed.
 *
 * The rule this encodes:
 *
 *   - Navigating, or changing what the page shows → `router.*`
 *   - Talking to a JSON endpoint from a widget → this
 *
 * CSRF comes from the `csrf-token` meta tag in app.blade.php. Laravel
 * also accepts the XSRF-TOKEN cookie, which is what Inertia and axios
 * use, but plain `fetch` sends neither automatically.
 */
export async function postJson<T = unknown>(
    url: string,
    body: Record<string, unknown> = {},
    method: 'POST' | 'DELETE' = 'POST',
): Promise<T> {
    const token =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';

    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            // Makes Laravel return JSON validation errors rather than a
            // redirect, so a 422 arrives as something parseable.
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        },
        // Session cookie. Same-origin only — this never talks to a third
        // party, and `include` would attach credentials if it ever did.
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        // Thrown rather than returned, so a caller that forgets to check
        // fails loudly instead of treating a 419 as success — which is
        // precisely how the missing CSRF meta tag stayed hidden.
        throw new Error(`${method} ${url} failed with ${response.status}`);
    }

    // 204 has no body; parsing it would throw on valid success.
    return response.status === 204
        ? (undefined as T)
        : ((await response.json()) as T);
}
