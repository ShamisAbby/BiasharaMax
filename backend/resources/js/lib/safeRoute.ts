/**
 * Resolves a Ziggy route name, or null if it can't be resolved.
 *
 * `route()` throws on an unknown name. That is fine at a call site with a
 * literal name — a typo is a build-time problem you find immediately — but
 * every place that resolves a name from a *list* is different: the sidebar,
 * the quick-create menu and the ⌘K palette all map over arrays of route
 * names during render, and a single bad entry there throws inside React's
 * render phase and blanks the entire application.
 *
 * That trade is never worth it. A missing shortcut is a nuisance; a blank
 * screen is an outage, and one that hides which entry caused it.
 */
export function safeRoute(name: string): string | null {
    try {
        return route(name);
    } catch (error) {
        // Logged rather than swallowed, so a route that has been renamed
        // shows up in the console for whoever renamed it instead of just
        // quietly vanishing from the menu.
        console.warn(`[nav] route "${name}" could not be resolved`, error);

        return null;
    }
}
