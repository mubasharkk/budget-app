import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { formatCurrency } from '@/utils/money';

const WINDOWS = [7, 14, 30];

const formatDate = (value) =>
    new Date(value).toLocaleDateString('de-DE', {
        month: 'short',
        day: 'numeric',
    });

const dueLabel = (days) => {
    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Tomorrow';
    }

    return `In ${days} days`;
};

const dueTone = (days) => {
    if (days <= 1) {
        return 'bg-red-100 text-red-700';
    }

    if (days <= 7) {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-gray-100 text-gray-600';
};

export default function UpcomingBillsWidget() {
    const [days, setDays] = useState(30);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError(null);

        axios
            .get(route('dashboard.upcoming-bills'), { params: { days } })
            .then((response) => {
                if (!cancelled) {
                    setData(response.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('Could not load upcoming bills.');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [days]);

    const bills = data?.bills ?? [];

    return (
        <div className="p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">
                        Upcoming bills
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Contract payments due in the next {days} days.
                    </p>
                </div>
                <div className="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
                    {WINDOWS.map((option) => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => setDays(option)}
                            className={`rounded px-3 py-1 text-sm font-medium transition ${
                                days === option
                                    ? 'bg-gray-800 text-white'
                                    : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            {option}d
                        </button>
                    ))}
                </div>
            </div>

            {error && <p className="mt-3 text-sm text-red-600">{error}</p>}

            <div className="mt-4 grid grid-cols-2 gap-3">
                <div className="rounded-lg bg-gray-50 px-4 py-3">
                    <div className="text-xs font-medium uppercase tracking-wider text-gray-500">
                        Due this week
                    </div>
                    <div className="mt-1 text-lg font-semibold text-gray-900">
                        {formatCurrency(data?.due_this_week.total ?? 0)}
                    </div>
                    <div className="text-xs text-gray-500">
                        {data?.due_this_week.count ?? 0}{' '}
                        {data?.due_this_week.count === 1 ? 'bill' : 'bills'}
                    </div>
                </div>
                <div className="rounded-lg bg-gray-50 px-4 py-3">
                    <div className="text-xs font-medium uppercase tracking-wider text-gray-500">
                        Next {days} days
                    </div>
                    <div className="mt-1 text-lg font-semibold text-gray-900">
                        {formatCurrency(data?.total ?? 0)}
                    </div>
                    <div className="text-xs text-gray-500">
                        {data?.count ?? 0}{' '}
                        {data?.count === 1 ? 'bill' : 'bills'}
                    </div>
                </div>
            </div>

            <div className="mt-4 max-h-80 overflow-y-auto rounded-lg border border-gray-100">
                {loading && !data ? (
                    <div className="space-y-3 p-4">
                        {[0, 1, 2].map((row) => (
                            <div
                                key={row}
                                className="h-6 animate-pulse rounded bg-gray-100"
                            />
                        ))}
                    </div>
                ) : bills.length === 0 ? (
                    <p className="p-6 text-center text-sm text-gray-500">
                        Nothing due in the next {days} days.
                    </p>
                ) : (
                    <ul
                        className={`divide-y divide-gray-100 ${loading ? 'opacity-50' : ''}`}
                    >
                        {bills.map((bill) => (
                            <li key={bill.contract_id}>
                                <Link
                                    href={route(
                                        'contracts.show',
                                        bill.contract_id,
                                    )}
                                    className="flex items-center gap-3 px-4 py-3 hover:bg-gray-50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-sm font-medium text-gray-900">
                                            {bill.name}
                                        </div>
                                        <div className="truncate text-xs text-gray-500">
                                            {formatDate(bill.due_date)}
                                            {bill.provider &&
                                                ` · ${bill.provider}`}
                                        </div>
                                    </div>
                                    <span
                                        className={`whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${dueTone(bill.days_until_due)}`}
                                    >
                                        {dueLabel(bill.days_until_due)}
                                    </span>
                                    <div className="w-24 whitespace-nowrap text-right text-sm font-semibold text-gray-900">
                                        {formatCurrency(bill.amount)}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Link
                href={route('contracts.index')}
                className="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800"
            >
                All contracts →
            </Link>
        </div>
    );
}
