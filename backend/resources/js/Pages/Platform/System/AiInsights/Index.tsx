import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiChart from '@/Components/Bi/BiChart';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { formatCurrency } from '@/lib/currency';
import { Link, router } from '@inertiajs/react';

interface ChurnRiskRow {
    business_id: string;
    business_name: string;
    risk_score: number;
    reasons: string[];
}

interface HealthScoreRow {
    business_id: string;
    business_name: string;
    health_score: number;
}

interface SavedInsight {
    id: string;
    title: string;
    summary: string | null;
    generated_by: string;
    created_at: string;
}

export default function AiInsightsIndex({
    revenueForecast,
    subscriptionForecast,
    churnRisk,
    businessHealthScores,
    growthTrend,
    mostActiveBusinesses,
    inactiveBusinesses,
    revenueByBusinessType,
    revenueByCountry,
    savedInsights,
    aiConfigured,
}: {
    revenueForecast: {
        history: Record<string, number>;
        forecast_next_month: number;
        method: string;
    };
    subscriptionForecast: {
        history: Record<string, number>;
        forecast_next_month: number;
        method: string;
    };
    churnRisk: ChurnRiskRow[];
    businessHealthScores: HealthScoreRow[];
    growthTrend: { this_month: { businesses: number; growth_percent: number } };
    mostActiveBusinesses: {
        business_id: string;
        name: string;
        transaction_count: number;
    }[];
    inactiveBusinesses: {
        business_id: string;
        name: string;
        days_inactive: number;
    }[];
    revenueByBusinessType: { business_type: string; total: number }[];
    revenueByCountry: { country: string; total: number }[];
    savedInsights: SavedInsight[];
    aiConfigured: boolean;
}) {
    const { notify } = useBiNotification();

    const generateNarrative = (type: string) => {
        router.post(
            route('platform.system.ai-insights.generate-narrative'),
            { type },
            {
                onSuccess: () => notify('Narrative generated.', 'success'),
                onError: (errors) => errors.ai && notify(errors.ai, 'error'),
            },
        );
    };

    return (
        <PlatformLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            AI Insights
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Statistics are computed directly from real platform
                            data.{' '}
                            {!aiConfigured && (
                                <>
                                    No AI provider configured —{' '}
                                    <Link
                                        href={route(
                                            'platform.system.integrations.index',
                                        )}
                                        className="text-indigo-600 hover:underline"
                                    >
                                        enable one in Integrations
                                    </Link>{' '}
                                    for narrative summaries.
                                </>
                            )}
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BiCard
                        title="Revenue Forecast"
                        description="Linear trend extrapolation, next month"
                        actions={
                            <BiButton
                                size="sm"
                                onClick={() =>
                                    generateNarrative('revenue_forecast')
                                }
                            >
                                Generate AI summary
                            </BiButton>
                        }
                    >
                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {formatCurrency(
                                revenueForecast.forecast_next_month,
                            )}
                        </p>
                        <BiChart
                            type="line"
                            labels={Object.keys(revenueForecast.history)}
                            datasets={[
                                {
                                    label: 'Revenue',
                                    data: Object.values(
                                        revenueForecast.history,
                                    ),
                                },
                            ]}
                            showLegend={false}
                        />
                    </BiCard>

                    <BiCard
                        title="Subscription Forecast"
                        description="Linear trend extrapolation, next month"
                    >
                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {subscriptionForecast.forecast_next_month}
                        </p>
                        <BiChart
                            type="bar"
                            labels={Object.keys(subscriptionForecast.history)}
                            datasets={[
                                {
                                    label: 'New subscriptions',
                                    data: Object.values(
                                        subscriptionForecast.history,
                                    ),
                                },
                            ]}
                            showLegend={false}
                        />
                    </BiCard>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <BiCard title="Growth This Month">
                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {growthTrend.this_month.businesses}
                        </p>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {growthTrend.this_month.growth_percent}% vs last
                            month
                        </p>
                    </BiCard>
                </div>

                <BiCard
                    title="Churn Risk"
                    description="Businesses with recency-based risk signals"
                    actions={
                        <BiButton
                            size="sm"
                            onClick={() => generateNarrative('churn_risk')}
                        >
                            Generate AI summary
                        </BiButton>
                    }
                >
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {churnRisk.map((row) => (
                            <div
                                key={row.business_id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <div>
                                    <p className="text-gray-900 dark:text-gray-100">
                                        {row.business_name}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {row.reasons.join(' · ')}
                                    </p>
                                </div>
                                <BiBadge
                                    variant={
                                        row.risk_score >= 60
                                            ? 'danger'
                                            : row.risk_score >= 30
                                              ? 'warning'
                                              : 'neutral'
                                    }
                                >
                                    {row.risk_score}% risk
                                </BiBadge>
                            </div>
                        ))}
                        {churnRisk.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No churn risk signals detected.
                            </p>
                        )}
                    </div>
                </BiCard>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BiCard
                        title="Most Active Businesses"
                        description="Last 30 days"
                    >
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {mostActiveBusinesses.map((b) => (
                                <div
                                    key={b.business_id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <span className="text-gray-900 dark:text-gray-100">
                                        {b.name}
                                    </span>
                                    <span className="text-gray-500 dark:text-gray-400">
                                        {b.transaction_count} transactions
                                    </span>
                                </div>
                            ))}
                            {mostActiveBusinesses.length === 0 && (
                                <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No data yet.
                                </p>
                            )}
                        </div>
                    </BiCard>

                    <BiCard title="Inactive Businesses">
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {inactiveBusinesses.map((b) => (
                                <div
                                    key={b.business_id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <span className="text-gray-900 dark:text-gray-100">
                                        {b.name}
                                    </span>
                                    <span className="text-gray-500 dark:text-gray-400">
                                        {b.days_inactive} days inactive
                                    </span>
                                </div>
                            ))}
                            {inactiveBusinesses.length === 0 && (
                                <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No data yet.
                                </p>
                            )}
                        </div>
                    </BiCard>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BiCard title="Revenue by Business Type">
                        {revenueByBusinessType.length > 0 ? (
                            <BiChart
                                type="doughnut"
                                labels={revenueByBusinessType.map(
                                    (r) => r.business_type,
                                )}
                                datasets={[
                                    {
                                        label: 'Revenue',
                                        data: revenueByBusinessType.map(
                                            (r) => r.total,
                                        ),
                                    },
                                ]}
                            />
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No revenue data yet.
                            </p>
                        )}
                    </BiCard>

                    <BiCard title="Revenue by Country">
                        {revenueByCountry.length > 0 ? (
                            <BiChart
                                type="doughnut"
                                labels={revenueByCountry.map((r) => r.country)}
                                datasets={[
                                    {
                                        label: 'Revenue',
                                        data: revenueByCountry.map(
                                            (r) => r.total,
                                        ),
                                    },
                                ]}
                            />
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No revenue data yet.
                            </p>
                        )}
                    </BiCard>
                </div>

                <BiCard title="Business Health Scores">
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {businessHealthScores.slice(0, 10).map((b) => (
                            <div
                                key={b.business_id}
                                className="flex items-center justify-between py-2 text-sm"
                            >
                                <span className="text-gray-900 dark:text-gray-100">
                                    {b.business_name}
                                </span>
                                <BiBadge
                                    variant={
                                        b.health_score >= 70
                                            ? 'success'
                                            : b.health_score >= 40
                                              ? 'warning'
                                              : 'danger'
                                    }
                                >
                                    {b.health_score}
                                </BiBadge>
                            </div>
                        ))}
                        {businessHealthScores.length === 0 && (
                            <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No data yet.
                            </p>
                        )}
                    </div>
                </BiCard>

                <BiCard title="Saved AI Recommendations">
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {savedInsights.map((insight) => (
                            <div key={insight.id} className="py-3 text-sm">
                                <p className="font-medium text-gray-900 dark:text-gray-100">
                                    {insight.title}
                                </p>
                                <p className="text-gray-600 dark:text-gray-400">
                                    {insight.summary}
                                </p>
                                <p className="mt-1 text-xs text-gray-400">
                                    {new Date(
                                        insight.created_at,
                                    ).toLocaleString()}
                                </p>
                            </div>
                        ))}
                        {savedInsights.length === 0 && (
                            <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No AI-generated recommendations yet.
                            </p>
                        )}
                    </div>
                </BiCard>
            </div>
        </PlatformLayout>
    );
}
