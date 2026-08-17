import { useEffect, useState } from 'react';

type Countdown = {
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
    expired: boolean;
};

function diffToCountdown(targetMs: number): Countdown {
    const remainingMs = Math.max(0, targetMs - Date.now());
    const totalSeconds = Math.floor(remainingMs / 1000);

    return {
        days: Math.floor(totalSeconds / 86400),
        hours: Math.floor((totalSeconds % 86400) / 3600),
        minutes: Math.floor((totalSeconds % 3600) / 60),
        seconds: totalSeconds % 60,
        expired: remainingMs <= 0,
    };
}

export function useCountdown(target: string | null): Countdown | null {
    const targetMs = target ? new Date(target).getTime() : null;
    const [countdown, setCountdown] = useState<Countdown | null>(
        targetMs ? diffToCountdown(targetMs) : null,
    );

    useEffect(() => {
        if (!targetMs) {
            setCountdown(null);
            return;
        }

        setCountdown(diffToCountdown(targetMs));
        const interval = setInterval(() => {
            setCountdown(diffToCountdown(targetMs));
        }, 1000);

        return () => clearInterval(interval);
    }, [targetMs]);

    return countdown;
}
