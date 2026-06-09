import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/money';

const statusAccent = {
    on_track: 'text-green-600',
    warning: 'text-amber-600',
    over: 'text-red-600',
};

function SnapshotCard({ title, value, subtitle, href, accent = 'text-gray-900' }) {
    return (
        <Link
            href={href}
            className="group rounded-lg border border-gray-100 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md"
        >
            <div className="text-xs font-medium uppercase tracking-wide text-gray-400">
                {title}
            </div>
            <div className={`mt-1 text-lg font-semibold ${accent}`}>{value}</div>
            {subtitle && (
                <div className="mt-1 text-xs text-gray-500 group-hover:text-gray-700">
                    {subtitle}
                </div>
            )}
        </Link>
    );
}

function Skeleton() {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            {[...Array(6)].map((_, i) => (
                <div
                    key={i}
                    className="h-24 animate-pulse rounded-lg bg-gray-200"
                />
            ))}
        </div>
    );
}

export default function DashboardAtAGlance() {
    const [period, setPeriod] = useState('month');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        axios
            .get('/dashboard/snapshot', { params: { period } })
            .then((res) => {
                if (!cancelled) setData(res.data);
            })
            .catch((error) =>
                console.error('Error fetching dashboard snapshot:', error),
            )
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [period]);

    const periodLabel = period === 'week' ? 'This week' : 'This month';

    return (
        <div className="space-y-4 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">
                        At a glance
                    </h3>
                    <p className="text-sm text-gray-500">
                        {periodLabel} — tap any card for details
                    </p>
                </div>
                <div className="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
                    {['month', 'week'].map((p) => (
                        <button
                            key={p}
                            type="button"
                            onClick={() => setPeriod(p)}
                            className={`rounded px-3 py-1 text-sm font-medium capitalize transition ${
                                period === p
                                    ? 'bg-gray-800 text-white'
                                    : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            {p}
                        </button>
                    ))}
                </div>
            </div>

            {loading || !data ? (
                <Skeleton />
            ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <SnapshotCard
                        title="Spending"
                        value={formatCurrency(data.expenses.total)}
                        subtitle={
                            data.expenses.delta !== 0
                                ? `${data.expenses.delta > 0 ? '+' : ''}${formatCurrency(data.expenses.delta)} vs prior ${period}`
                                : `${formatCurrency(data.expenses.variable)} variable`
                        }
                        href={data.expenses.href}
                    />
                    <SnapshotCard
                        title="Budgets"
                        value={
                            data.budgets.over_count > 0
                                ? `${data.budgets.over_count} over`
                                : data.budgets.warning_count > 0
                                  ? `${data.budgets.warning_count} near limit`
                                  : 'On track'
                        }
                        subtitle={`${formatCurrency(data.budgets.actual)} of ${formatCurrency(data.budgets.budgeted)}`}
                        href={data.budgets.href}
                        accent={statusAccent[data.budgets.status]}
                    />
                    <SnapshotCard
                        title="Savings"
                        value={
                            data.savings.opportunity_count > 0
                                ? formatCurrency(data.savings.potential_total)
                                : '—'
                        }
                        subtitle={
                            data.savings.opportunity_count > 0
                                ? `${data.savings.opportunity_count} opportunities`
                                : 'No overpayments yet'
                        }
                        href={data.savings.href}
                        accent="text-emerald-600"
                    />
                    <SnapshotCard
                        title="Top item"
                        value={data.consumption.top_item ?? '—'}
                        subtitle={
                            data.consumption.top_spend
                                ? formatCurrency(data.consumption.top_spend)
                                : 'Upload receipts'
                        }
                        href={data.consumption.href}
                    />
                    <SnapshotCard
                        title="Contracts"
                        value={formatCurrency(data.contracts.monthly_fixed)}
                        subtitle={
                            data.contracts.due_soon > 0
                                ? `${data.contracts.due_soon} due soon`
                                : 'Fixed / month'
                        }
                        href={data.contracts.href}
                        accent="text-indigo-600"
                    />
                    <SnapshotCard
                        title="Assistant"
                        value={
                            data.agent.anomaly_count > 0
                                ? `${data.agent.anomaly_count} alerts`
                                : data.agent.recommendation_count > 0
                                  ? `${data.agent.recommendation_count} tips`
                                  : 'All clear'
                        }
                        subtitle={
                            data.agent.digest_period
                                ? `Digest: ${data.agent.digest_period}`
                                : data.agent.renewal_count > 0
                                  ? `${data.agent.renewal_count} renewals`
                                  : 'Ask a question'
                        }
                        href={data.agent.href}
                    />
                </div>
            )}
        </div>
    );
}
