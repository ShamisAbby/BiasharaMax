import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';

/**
 * The floating "talk to us" button.
 *
 * Two decisions worth recording.
 *
 * It appears after a short delay rather than on load. A support button
 * that is already there when the page paints competes with the hero for
 * the first thing you look at; one that arrives a moment later is noticed
 * without having interrupted anything.
 *
 * The label expands once, on its own, and then collapses to the icon. A
 * bare green circle is only recognisable to people who already know what
 * it is, and a permanently expanded pill covers content on a phone for
 * the entire visit. Showing it once buys the recognition without the
 * cost — and it can be reopened by hovering, so nothing is lost.
 */
export default function WhatsAppButton({
    href,
    label = 'Chat with us',
}: {
    href: string;
    label?: string;
}) {
    const [visible, setVisible] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const [hovered, setHovered] = useState(false);

    useEffect(() => {
        const appear = window.setTimeout(() => {
            setVisible(true);
            setExpanded(true);
        }, 1800);

        const collapse = window.setTimeout(() => setExpanded(false), 6000);

        // Cleared on unmount. Without this, navigating away inside the
        // delay leaves a timer that calls setState on a dead component.
        return () => {
            window.clearTimeout(appear);
            window.clearTimeout(collapse);
        };
    }, []);

    const open = expanded || hovered;

    return (
        <AnimatePresence>
            {visible && (
                <motion.a
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={label}
                    initial={{ opacity: 0, scale: 0.6, y: 16 }}
                    animate={{ opacity: 1, scale: 1, y: 0 }}
                    exit={{ opacity: 0, scale: 0.6 }}
                    transition={{ type: 'spring', stiffness: 300, damping: 20 }}
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    onHoverStart={() => setHovered(true)}
                    onHoverEnd={() => setHovered(false)}
                    // `bottom-6 right-6` on a landing page that uses scroll
                    // snapping: fixed, so it does not travel with the
                    // snapping sections and re-animate at every stop.
                    className="group fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-full bg-[#25D366] py-3 pe-4 ps-3 text-white shadow-lg shadow-black/20 transition-colors hover:bg-[#20BA5A] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#25D366]/40"
                >
                    <span className="relative flex h-7 w-7 items-center justify-center">
                        {/*
                          The pulse. `motion.span` rather than Tailwind's
                          animate-ping so it can be slowed right down —
                          ping's one-second cycle next to a phone number
                          reads as urgency, which is the wrong tone for
                          "we are here if you need us".
                        */}
                        <motion.span
                            aria-hidden="true"
                            className="absolute inset-0 rounded-full bg-white/40"
                            animate={{ scale: [1, 1.8], opacity: [0.5, 0] }}
                            transition={{
                                duration: 2.4,
                                repeat: Infinity,
                                ease: 'easeOut',
                            }}
                        />
                        <WhatsAppGlyph className="relative h-7 w-7" />
                    </span>

                    {/*
                      Width animated rather than toggled, so the label
                      slides out of the circle instead of the button
                      jumping between two sizes.
                    */}
                    <motion.span
                        initial={false}
                        animate={{
                            width: open ? 'auto' : 0,
                            opacity: open ? 1 : 0,
                        }}
                        transition={{ duration: 0.25, ease: 'easeOut' }}
                        className="overflow-hidden whitespace-nowrap text-sm font-semibold"
                    >
                        {label}
                    </motion.span>
                </motion.a>
            )}
        </AnimatePresence>
    );
}

/**
 * WhatsApp's own mark, inline.
 *
 * Heroicons has no brand glyphs, and a generic speech bubble would not
 * tell anyone which app the button opens — which is the single thing this
 * button needs to communicate before it is clicked.
 */
function WhatsAppGlyph({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
            className={className}
        >
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.82 9.82 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.82 11.82 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413Z" />
        </svg>
    );
}
