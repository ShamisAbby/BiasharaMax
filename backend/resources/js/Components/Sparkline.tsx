const WIDTH = 100;
const HEIGHT = 32;

/** Minimal inline SVG trend line — no chart library overhead for an 8px-tall stat-card accent. */
export default function Sparkline({
    data,
    stroke = '#6366F1',
    fill = 'rgba(99, 102, 241, 0.15)',
}: {
    data: number[];
    stroke?: string;
    fill?: string;
}) {
    if (data.length < 2) {
        return null;
    }

    const min = Math.min(...data);
    const max = Math.max(...data);
    const range = max - min || 1;

    const points = data.map((value, index) => {
        const x = (index / (data.length - 1)) * WIDTH;
        const y = HEIGHT - ((value - min) / range) * HEIGHT;
        return [x, y];
    });

    const linePath = points
        .map(
            ([x, y], index) =>
                `${index === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`,
        )
        .join(' ');
    const areaPath = `${linePath} L${WIDTH},${HEIGHT} L0,${HEIGHT} Z`;

    return (
        <svg
            viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
            className="h-8 w-full"
            preserveAspectRatio="none"
        >
            <path d={areaPath} fill={fill} stroke="none" />
            <path
                d={linePath}
                fill="none"
                stroke={stroke}
                strokeWidth={2}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
