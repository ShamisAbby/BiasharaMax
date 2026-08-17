import { useDarkMode } from '@/hooks/useDarkMode';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { Bar } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

type Point = { label: string; amount: number };

export default function MonthlySalesBarChart({ data }: { data: Point[] }) {
    const { isDark } = useDarkMode();
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#9CA3AF' : '#6B7280';

    return (
        <Bar
            data={{
                labels: data.map((point) => point.label),
                datasets: [
                    {
                        label: 'Sales',
                        data: data.map((point) => point.amount),
                        backgroundColor: data.map((_, index) =>
                            index % 2 === 0 ? '#8B5CF6' : '#3B82F6',
                        ),
                        borderRadius: 6,
                    },
                ],
            }}
            options={{
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
                        grid: { display: false },
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
