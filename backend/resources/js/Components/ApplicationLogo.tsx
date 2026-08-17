import { SVGAttributes } from 'react';

/**
 * The BiasharaMax mark: a dhow under sail.
 *
 * Two reasons for a dhow rather than the usual abstract chart or monogram.
 * It is the most recognisable thing in Zanzibar, which is now the whole
 * market — a mark a shopkeeper in Stone Town recognises before they have
 * read the name is worth more than a shape that would suit a SaaS company
 * anywhere. And the two sails ascend left to right, so it reads as growth
 * without having to draw a literal arrow.
 *
 * Deliberately one colour, no gradients, no strokes.
 *
 *  * Every existing usage passes `fill-current` alongside a text colour
 *    (`text-white` in the dark footer, `text-indigo-600` in the sidebar),
 *    so the mark has to inherit whatever colour it is dropped into. A
 *    gradient or a hardcoded fill would break the moment it moved.
 *  * Strokes do not scale with the viewBox the way fills do, so a
 *    stroked version would look heavy in the 24px sidebar and thin on a
 *    login screen.
 *
 * The shapes stay above ~1.5 units in a 32-unit box, which is what keeps
 * it legible at the 16px favicon size rather than collapsing to a smudge.
 */
export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="currentColor"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="BiasharaMax"
        >
            {/* Mainsail — the curve on the leech is what stops it reading
                as a plain triangle. */}
            <path d="M17.4 3.6c0-.7.9-1 1.3-.4 3.6 5 5.8 10.8 6.4 16.9.1.6-.4 1.1-1 1.1h-5.6c-.6 0-1.1-.5-1.1-1.1V3.6Z" />

            {/* Jib. Shorter and set forward, which gives the mark its
                left-to-right rise. */}
            <path d="M14.6 8.3c0-.7-.9-1-1.3-.5-2.8 3.4-4.7 7.4-5.6 11.8-.1.6.4 1.2 1 1.2h4.8c.6 0 1.1-.5 1.1-1.1V8.3Z" />

            {/* Hull. Doubles as the baseline a chart would sit on, which is
                the second reading of the mark. */}
            <path d="M4.2 23.4h23.6c.8 0 1.3.8 1 1.5l-1.8 3.5c-.3.6-.9.9-1.5.9H7.5c-.7 0-1.3-.4-1.6-1l-1.7-3.4c-.3-.7.2-1.5 1-1.5Z" />
        </svg>
    );
}
