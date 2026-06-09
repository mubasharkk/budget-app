import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/money';

function Skeleton() {
    return <div className="h-28 animate-pulse rounded-lg bg-gray-200" />;
}

function ProgressBar({ label, percent, amount, currency, accent }) {
    const width = Math.min(percent ?? 0, 100);

    return (
        <div>
            <div className="flex justify-between text-sm">
                <span className="font-medium text-gray-700">{label}</span>
                <span className="text-gray-600">
                    {formatCurrency(amount, currency)}
                    {percent !== null && (
                        <span className="ml-1 text-gray-400">
                            ({percent}%)
                        </span>
                    )}
                </span>
            </div>
            <div className="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                <div
                    className={`h-full ${accent}`}
                    style={{ width: `${width}%` }}
                />
            </div>
        </div>
    );
}

export default function IncomeOverview() {
    const [period, setPeriod] = useState('monthly');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        axios
            .get('/dashboard/budgets', { params: { period } })
            .then((res) => {
                if (!cancelled) setData(res.data);
            })
            .catch((error) =>
                console.error('Error fetching income overview:', error),
            )
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [period]);

    const income = data?.income;
    const periodLabel = period === 'weekly' ? 'This week' : 'This month';

    return (
        <div className="space-y-4 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">
                        Income vs spending
                    </h3>
                    <p className="text-sm text-gray-500">
                        Compare {periodLabel.toLowerCase()} spend and budgets
                        against your income.
                    </p>
                </div>
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
            </div>

            {loading || !data ? (
                <Skeleton />
            ) : !income ? (
                <div className="rounded-lg border border-dashed border-gray-200 py-10 text-center">
                    <p className="text-sm text-gray-500">
                        Add your monthly net or brutto income to see how your
                        budget fits your earnings.
                    </p>
                    <Link
                        href={route('profile.edit')}
                        className="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Set income in profile
                    </Link>
                </div>
            ) : (
                <>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div className="rounded-lg bg-indigo-50 p-4">
                            <div className="text-sm text-indigo-600">
                                {periodLabel} income ({income.income_type_label})
                            </div>
                            <div className="mt-1 text-xl font-semibold text-gray-900">
                                {formatCurrency(
                                    income.period_income,
                                    income.currency,
                                )}
                            </div>
                            <div className="mt-1 text-xs text-gray-500">
                                {formatCurrency(
                                    income.monthly_income,
                                    income.currency,
                                )}{' '}
                                / month
                            </div>
                        </div>
                        <div className="rounded-lg bg-gray-50 p-4">
                            <div className="text-sm text-gray-500">
                                Actual spend
                            </div>
                            <div
                                className={`mt-1 text-xl font-semibold ${
                                    income.is_over_income
                                        ? 'text-red-600'
                                        : 'text-gray-900'
                                }`}
                            >
                                {formatCurrency(data.actual, income.currency)}
                            </div>
                            <div className="mt-1 text-xs text-gray-500">
                                {income.spend_percent}% of income
                            </div>
                        </div>
                        <div className="rounded-lg bg-gray-50 p-4">
                            <div className="text-sm text-gray-500">
                                Budgeted
                            </div>
                            <div className="mt-1 text-xl font-semibold text-gray-900">
                                {formatCurrency(data.budgeted, income.currency)}
                            </div>
                            <div className="mt-1 text-xs text-gray-500">
                                {income.budgeted_percent}% of income
                            </div>
                        </div>
                        <div className="rounded-lg bg-gray-50 p-4">
                            <div className="text-sm text-gray-500">
                                Left after spend
                            </div>
                            <div
                                className={`mt-1 text-xl font-semibold ${
                                    income.disposable < 0
                                        ? 'text-red-600'
                                        : 'text-green-600'
                                }`}
                            >
                                {formatCurrency(
                                    income.disposable,
                                    income.currency,
                                )}
                            </div>
                            <div className="mt-1 text-xs text-gray-500">
                                {formatCurrency(
                                    income.remaining_after_budgets,
                                    income.currency,
                                )}{' '}
                                after budgets
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <ProgressBar
                            label="Spending"
                            percent={income.spend_percent}
                            amount={data.actual}
                            currency={income.currency}
                            accent={
                                income.is_over_income
                                    ? 'bg-red-500'
                                    : 'bg-emerald-500'
                            }
                        />
                        <ProgressBar
                            label="Budget allocation"
                            percent={income.budgeted_percent}
                            amount={data.budgeted}
                            currency={income.currency}
                            accent={
                                income.budgets_exceed_income
                                    ? 'bg-amber-500'
                                    : 'bg-indigo-500'
                            }
                        />
                    </div>
                </>
            )}
        </div>
    );
}
