import { useEffect, useRef, useState } from 'react';

/**
 * Animates from 0 (or the previous value) to `value` over ~600ms. Purely
 * cosmetic — the underlying number is always the real value passed in, this
 * just eases the visual transition so widgets feel alive on load/refresh.
 */
export default function BiAnimatedCounter({
    value,
    formatter,
}: {
    value: number;
    formatter?: (value: number) => string;
}) {
    const [display, setDisplay] = useState(0);
    const previousValue = useRef(0);

    useEffect(() => {
        const from = previousValue.current;
        const to = value;
        const duration = 600;
        const start = performance.now();

        let frame: number;

        const tick = (now: number) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            setDisplay(Math.round(from + (to - from) * eased));

            if (progress < 1) {
                frame = requestAnimationFrame(tick);
            } else {
                previousValue.current = to;
            }
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [value]);

    return <>{formatter ? formatter(display) : display.toLocaleString()}</>;
}
