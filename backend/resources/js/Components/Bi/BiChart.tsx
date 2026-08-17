import { useDarkMode } from '@/hooks/useDarkMode';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { Bar, Doughnut, Line } from 'react-chartjs-2';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Tooltip,
    Legend,
);

const PALETTE = [
    '#4F46E5',
    '#3B82F6',
    '#8B5CF6',
    '#06B6D4',
    '#10B981',
    '#F59E0B',
    '#EF4444',
    '#6B7280',
];

export interface BiChartDataset {
    label: string;
    data: number[];
    color?: string;
}

export default function BiChart({
    type,
    labels,
    datasets,
    height = 256,
    showLegend = true,
}: {
    type: 'line' | 'bar' | 'doughnut';
    labels: string[];
    datasets: BiChartDataset[];
    height?: number;
    showLegend?: boolean;
}) {
    const { isDark } = useDarkMode();
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#9CA3AF' : '#6B7280';

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: showLegend,
                position: 'bottom' as const,
                labels: { color: textColor },
            },
        },
    };

    const wrapperStyle = { height };

    if (type === 'doughnut') {
        return (
            <div style={wrapperStyle}>
                <Doughnut
                    data={{
                        labels,
                        datasets: [
                            {
                                data: datasets[0]?.data ?? [],
                                backgroundColor: labels.map(
                                    (_, i) => PALETTE[i % PALETTE.length],
                                ),
                                borderWidth: 0,
                            },
                        ],
                    }}
                    options={{ ...commonOptions, cutout: '70%' }}
                />
            </div>
        );
    }

    const chartDatasets = datasets.map((dataset, index) => ({
        label: dataset.label,
        data: dataset.data,
        borderColor: dataset.color ?? PALETTE[index % PALETTE.length],
        backgroundColor:
            type === 'line'
                ? (dataset.color ?? PALETTE[index % PALETTE.length]) + '26'
                : (dataset.color ?? PALETTE[index % PALETTE.length]),
        tension: 0.4,
        fill: type === 'line',
        borderRadius: type === 'bar' ? 6 : undefined,
    }));

    const scales = {
        x: { grid: { display: false }, ticks: { color: textColor } },
        y: {
            grid: { color: gridColor },
            ticks: { color: textColor },
            beginAtZero: true,
        },
    };

    return (
        <div style={wrapperStyle}>
            {type === 'line' ? (
                <Line
                    data={{ labels, datasets: chartDatasets }}
                    options={{ ...commonOptions, scales }}
                />
            ) : (
                <Bar
                    data={{ labels, datasets: chartDatasets }}
                    options={{ ...commonOptions, scales }}
                />
            )}
        </div>
    );
}
