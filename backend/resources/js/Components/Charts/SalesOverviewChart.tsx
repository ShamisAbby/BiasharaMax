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

type TrendPoint = { label: string; sales: number; profit: number };

export default function SalesOverviewChart({ data }: { data: TrendPoint[] }) {
    const { isDark } = useDarkMode();
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#9CA3AF' : '#6B7280';

    return (
        <Line
            data={{
                labels: data.map((point) => point.label),
                datasets: [
                    {
                        label: 'Sales',
                        data: data.map((point) => point.sales),
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.15)',
                        tension: 0.4,
                        fill: true,
                    },
                    {
                        label: 'Profit',
                        data: data.map((point) => point.profit),
                        borderColor: '#8B5CF6',
                        backgroundColor: 'rgba(139, 92, 246, 0.15)',
                        tension: 0.4,
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
