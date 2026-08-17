import { useDarkMode } from '@/hooks/useDarkMode';
import {
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { Line } from 'react-chartjs-2';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
);

type TrendPoint = { date: string; inbound: number; outbound: number };

export default function StockMovementTrendChart({
    data,
}: {
    data: TrendPoint[];
}) {
    const { isDark } = useDarkMode();
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#9CA3AF' : '#6B7280';

    return (
        <Line
            data={{
                labels: data.map((point) =>
                    new Date(point.date).toLocaleDateString(undefined, {
                        month: 'short',
                        day: 'numeric',
                    }),
                ),
                datasets: [
                    {
                        label: 'Stock in',
                        data: data.map((point) => point.inbound),
                        borderColor: '#4F46E5',
                        backgroundColor: 'rgba(79, 70, 229, 0.15)',
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Stock out',
                        data: data.map((point) => point.outbound),
                        borderColor: '#F59E0B',
                        backgroundColor: 'rgba(245, 158, 11, 0.15)',
                        tension: 0.3,
                        fill: true,
                    },
                ],
            }}
            options={{
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: textColor } },
                },
                scales: {
                    x: {
                        grid: { color: gridColor },
                        ticks: { color: textColor },
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: textColor },
                        beginAtZero: true,
                    },
                },
            }}
        />
    );
}
