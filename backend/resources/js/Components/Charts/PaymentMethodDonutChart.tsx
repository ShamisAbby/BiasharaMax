import { useDarkMode } from '@/hooks/useDarkMode';
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { Doughnut } from 'react-chartjs-2';

ChartJS.register(ArcElement, Tooltip, Legend);

type Method = { label: string; percentage: number };

const COLORS = ['#3B82F6', '#6366F1', '#8B5CF6', '#06B6D4'];

export default function PaymentMethodDonutChart({ data }: { data: Method[] }) {
    const { isDark } = useDarkMode();
    const textColor = isDark ? '#9CA3AF' : '#6B7280';

    return (
        <Doughnut
            data={{
                labels: data.map((method) => method.label),
                datasets: [
                    {
                        data: data.map((method) => method.percentage),
                        backgroundColor: COLORS,
                        borderWidth: 0,
                    },
                ],
            }}
            options={{
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: textColor },
                    },
                },
            }}
        />
    );
}
