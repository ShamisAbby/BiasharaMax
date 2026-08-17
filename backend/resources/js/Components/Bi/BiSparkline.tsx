export default function BiSparkline({
    data,
    tone = 'positive',
    width = 96,
    height = 32,
}: {
    data: number[];
    tone?: 'positive' | 'negative' | 'neutral';
    width?: number;
    height?: number;
}) {
    if (data.length < 2) {
        return <div style={{ width, height }} />;
    }

    const min = Math.min(...data);
    const max = Math.max(...data);
    const range = max - min || 1;
    const stepX = width / (data.length - 1);

    const points = data
        .map((value, index) => {
            const x = index * stepX;
            const y = height - ((value - min) / range) * (height - 4) - 2;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');

    const color = {
        positive: '#10B981',
        negative: '#EF4444',
        neutral: '#94A3B8',
    }[tone];

    const areaPoints = `0,${height} ${points} ${width},${height}`;

    return (
        <svg
            width={width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
            className="overflow-visible"
        >
            <polyline
                points={areaPoints}
                fill={color}
                opacity={0.08}
                stroke="none"
            />
            <polyline
                points={points}
                fill="none"
                stroke={color}
                strokeWidth={1.75}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
