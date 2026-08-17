import { useDarkMode } from '@/hooks/useDarkMode';
import { ProfitTrendPoint } from '@/types/accounting';
import {
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LineElement,
    LinearScale,
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

export default function ProfitTrendLineChart({
    data,
}: {
    data: ProfitTrendPoint[];
}) {
    const { isDark } = useDarkMode();
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#9CA3AF' : '#6B7280';

    return (
        <Line
            data={{
                labels: data.map((point) => point.label),
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.map((point) => point.revenue),
                        borderColor: '#3B82F6',
                        backgroundColor: '#3B82F6',
                        tension: 0.3,
                    },
                    {
                        label: 'Expenses',
                        data: data.map((point) => point.expenses),
                        borderColor: '#F43F5E',
                        backgroundColor: '#F43F5E',
                        tension: 0.3,
                    },
                    {
                        label: 'Profit',
                        data: data.map((point) => point.profit),
                        borderColor: '#10B981',
                        backgroundColor: '#10B981',
                        tension: 0.3,
                    },
                ],
            }}
            options={{
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, labels: { color: textColor } },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor },
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: textColor },
                    },
                },
            }}
        />
    );
}
