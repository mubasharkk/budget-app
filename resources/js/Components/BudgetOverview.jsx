import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/money';

const statusStyles = {
    on_track: 'bg-green-500',
    warning: 'bg-amber-500',
    over: 'bg-red-500',
};

function Skeleton() {
    return <div className="h-32 animate-pulse rounded-lg bg-gray-200" />;
}

export default function BudgetOverview() {
    const [period, setPeriod] = useState('monthly');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        axios
            .get('/dashboard/budgets', {
                params: { period },
            })
            .then((res) => {
                if (!cancelled) setData(res.data);
            })
            .catch((error) =>
                console.error('Error fetching budget overview:', error),
            )
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [period]);

    const items = data?.items ?? [];
    const topItems = items.slice(0, 4);

    return (
        <div className="space-y-4 p-6">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900">
                    Budget vs actual
                </h3>
                <div className="flex items-center gap-3">
                    <div className="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
                        {[
                            { key: 'monthly', label: 'Month' },
                            { key: 'weekly', label: 'Week' },
                        ].map((p) => (
                            <button
                                key={p.key}
                                type="button"
                                onClick={() => setPeriod(p.key)}
                                className={`rounded px-3 py-1 text-sm font-medium transition ${
                                    period === p.key
                                        ? 'bg-gray-800 text-white'
                                        : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                {p.label}
                            </button>
                        ))}
                    </div>
                    <Link
                        href={route('budgets.index', { period })}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Manage
                    </Link>
                </div>
            </div>

            {loading || !data ? (
                <Skeleton />
            ) : items.length === 0 ? (
                <div className="rounded-lg border border-dashed border-gray-200 py-10 text-center">
                    <p className="text-sm text-gray-500">
                        No budgets set for this period.
                    </p>
                    <Link
                        href={route('budgets.create')}
                        className="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Add a budget
                    </Link>
                </div>
            ) : (
                <>
                    <div
                        className={`grid grid-cols-1 gap-4 ${
                            data.income
                                ? 'sm:grid-cols-2 lg:grid-cols-4'
                                : 'sm:grid-cols-3'
                        }`}
                    >
                        {data.income && (
                            <div className="rounded-lg bg-indigo-50 p-4">
                                <div className="text-sm text-indigo-600">
                                    Income ({data.income.income_type_label})
                                </div>
                                <div className="mt-1 text-xl font-semibold text-gray-900">
                                    {formatCurrency(
                                        data.income.period_income,
                                        data.income.currency,
                                    )}
                                </div>
                                <div className="mt-1 text-xs text-gray-500">
                                    {data.income.budgeted_percent}% budgeted
                                </div>
                            </div>
                        )}
                        <div className="rounded-lg bg-gray-50 p-4">
                            <div className="text-sm text-gray-500">Budgeted</div>
                            <div className="mt-1 text-xl font-semibold text-gray-900">
                                {formatCurrency(data.budgeted)}
                            </div>
                        </div>
                        <div className="rounded-lg bg-gray-50 p-4">
                            <div className="text-sm text-gray-500">Actual</div>
                            <div className="mt-1 text-xl font-semibold text-gray-900">
                                {formatCurrency(data.actual)}
                            </div>
                            {data.income && (
                                <div className="mt-1 text-xs text-gray-500">
                                    {data.income.spend_percent}% of income
                                </div>
                            )}
                        </div>
                        <div className="rounded-lg bg-gray-50 p-4">
                            <div className="text-sm text-gray-500">Status</div>
                            <div className="mt-1 text-xl font-semibold text-gray-900">
                                {data.over_count > 0 ? (
                                    <span className="text-red-600">
                                        {data.over_count} over
                                    </span>
                                ) : data.warning_count > 0 ? (
                                    <span className="text-amber-600">
                                        {data.warning_count} near limit
                                    </span>
                                ) : (
                                    <span className="text-green-600">
                                        On track
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    <ul className="space-y-3">
                        {topItems.map((item) => {
                            const barColor =
                                statusStyles[item.status] ??
                                statusStyles.on_track;
                            const percent = Math.min(item.percent_used, 100);

                            return (
                                <li key={item.budget_id}>
                                    <div className="flex justify-between text-sm">
                                        <span className="font-medium text-gray-700">
                                            {item.label}
                                        </span>
                                        <span className="text-gray-600">
                                            {formatCurrency(
                                                item.actual,
                                                item.currency,
                                            )}{' '}
                                            /{' '}
                                            {formatCurrency(
                                                item.budget_amount,
                                                item.currency,
                                            )}
                                        </span>
                                    </div>
                                    <div className="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                        <div
                                            className={`h-full ${barColor}`}
                                            style={{ width: `${percent}%` }}
                                        />
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </>
            )}
        </div>
    );
}
