import { useDarkMode } from '@/hooks/useDarkMode';
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js';
import { Doughnut } from 'react-chartjs-2';

ChartJS.register(ArcElement, Tooltip, Legend);

type Breakdown = {
    healthy: number;
    low_stock: number;
    out_of_stock: number;
    expiring_soon: number;
    expired: number;
};

const SEGMENTS: Array<{ key: keyof Breakdown; label: string; color: string }> =
    [
        { key: 'healthy', label: 'Healthy', color: '#10B981' },
        { key: 'low_stock', label: 'Low stock', color: '#F59E0B' },
        { key: 'out_of_stock', label: 'Out of stock', color: '#EF4444' },
        { key: 'expiring_soon', label: 'Expiring soon', color: '#3B82F6' },
        { key: 'expired', label: 'Expired', color: '#6B7280' },
    ];

export default function StockHealthDonutChart({ data }: { data: Breakdown }) {
    const { isDark } = useDarkMode();
    const textColor = isDark ? '#9CA3AF' : '#6B7280';
    const total = SEGMENTS.reduce((sum, segment) => sum + data[segment.key], 0);

    if (total === 0) {
        return (
            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No products yet.
            </p>
        );
    }

    return (
        <Doughnut
            data={{
                labels: SEGMENTS.map((segment) => segment.label),
                datasets: [
                    {
                        data: SEGMENTS.map((segment) => data[segment.key]),
                        backgroundColor: SEGMENTS.map(
                            (segment) => segment.color,
                        ),
                        borderWidth: 0,
                    },
                ],
            }}
            options={{
                responsive: true,
                maintainAspectRatio: false,
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
