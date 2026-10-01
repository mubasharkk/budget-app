import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import ReceiptKindIcon from '@/Components/ReceiptKindIcon';
import { formatCurrency } from '@/utils/money';

const toInputDate = (date) => {
    const offsetMs = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offsetMs).toISOString().slice(0, 10);
};

const PRESETS = [
    {
        key: 'this_month',
        label: 'This month',
        range: (today) => [
            new Date(today.getFullYear(), today.getMonth(), 1),
            new Date(today.getFullYear(), today.getMonth() + 1, 0),
        ],
    },
    {
        key: 'last_month',
        label: 'Last month',
        range: (today) => [
            new Date(today.getFullYear(), today.getMonth() - 1, 1),
            new Date(today.getFullYear(), today.getMonth(), 0),
        ],
    },
    {
        key: 'this_year',
        label: 'This year',
        range: (today) => [
            new Date(today.getFullYear(), 0, 1),
            new Date(today.getFullYear(), 11, 31),
        ],
    },
];

const SOURCE_LABELS = {
    receipt: 'Receipt',
    contract: 'Contract',
    income: 'Income',
};

const formatDate = (value) =>
    new Date(value).toLocaleDateString('de-DE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

const entryHref = (entry) => {
    if (entry.source === 'receipt') {
        return route('receipts.show', entry.id);
    }

    if (entry.source === 'contract') {
        return route('contracts.show', entry.id);
    }

    return route('incomes.edit', entry.id);
};

function Total({ label, value, tone }) {
    return (
        <div className="rounded-lg bg-gray-50 px-4 py-3">
            <div className="text-xs font-medium uppercase tracking-wider text-gray-500">
                {label}
            </div>
            <div className={`mt-1 text-lg font-semibold ${tone}`}>{value}</div>
        </div>
    );
}

export default function TransactionsWidget() {
    const [initialStart, initialEnd] = PRESETS[0].range(new Date());
    const [startDate, setStartDate] = useState(toInputDate(initialStart));
    const [endDate, setEndDate] = useState(toInputDate(initialEnd));
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const invalidRange = Boolean(startDate && endDate && endDate < startDate);

    useEffect(() => {
        if (!startDate || !endDate || invalidRange) {
            return undefined;
        }

        let cancelled = false;
        setLoading(true);
        setError(null);

        axios
            .get(route('dashboard.transactions'), {
                params: { start_date: startDate, end_date: endDate },
            })
            .then((response) => {
                if (!cancelled) {
                    setData(response.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('Could not load income and expenses.');
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
    }, [startDate, endDate, invalidRange]);

    const applyPreset = (preset) => {
        const [start, end] = preset.range(new Date());
        setStartDate(toInputDate(start));
        setEndDate(toInputDate(end));
    };

    const transactions = data?.transactions ?? [];
    const dateInputClasses =
        'mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

    return (
        <div className="p-6">
            <h3 className="text-lg font-medium text-gray-900">
                Income &amp; expenses
            </h3>
            <p className="mt-1 text-sm text-gray-500">
                Every recorded income and expense entry in the selected dates.
            </p>

            <div className="mt-4 flex flex-wrap items-end gap-3">
                <label className="text-sm font-medium text-gray-700">
                    From
                    <input
                        type="date"
                        value={startDate}
                        max={endDate || undefined}
                        onChange={(e) => setStartDate(e.target.value)}
                        className={dateInputClasses}
                    />
                </label>
                <label className="text-sm font-medium text-gray-700">
                    To
                    <input
                        type="date"
                        value={endDate}
                        min={startDate || undefined}
                        onChange={(e) => setEndDate(e.target.value)}
                        className={dateInputClasses}
                    />
                </label>
                <div className="flex flex-wrap gap-2">
                    {PRESETS.map((preset) => (
                        <button
                            key={preset.key}
                            type="button"
                            onClick={() => applyPreset(preset)}
                            className="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                        >
                            {preset.label}
                        </button>
                    ))}
                </div>
            </div>

            {invalidRange && (
                <p className="mt-3 text-sm text-red-600">
                    The end date must be on or after the start date.
                </p>
            )}
            {error && <p className="mt-3 text-sm text-red-600">{error}</p>}

            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <Total
                    label="Income"
                    value={formatCurrency(data?.totals.income ?? 0)}
                    tone="text-green-600"
                />
                <Total
                    label="Expenses"
                    value={formatCurrency(data?.totals.expenses ?? 0)}
                    tone="text-red-600"
                />
                <Total
                    label="Net"
                    value={formatCurrency(data?.totals.net ?? 0)}
                    tone={
                        (data?.totals.net ?? 0) < 0
                            ? 'text-red-600'
                            : 'text-gray-900'
                    }
                />
            </div>

            <div className="mt-4 max-h-96 overflow-y-auto rounded-lg border border-gray-100">
                {loading && !data ? (
                    <div className="space-y-3 p-4">
                        {[0, 1, 2, 3].map((row) => (
                            <div
                                key={row}
                                className="h-6 animate-pulse rounded bg-gray-100"
                            />
                        ))}
                    </div>
                ) : transactions.length === 0 ? (
                    <p className="p-6 text-center text-sm text-gray-500">
                        No income or expenses recorded in these dates.
                    </p>
                ) : (
                    <ul
                        className={`divide-y divide-gray-100 ${loading ? 'opacity-50' : ''}`}
                    >
                        {transactions.map((entry) => (
                            <li key={entry.key}>
                                <Link
                                    href={entryHref(entry)}
                                    className="flex items-center gap-3 px-4 py-3 hover:bg-gray-50"
                                >
                                    <ReceiptKindIcon kind={entry.type} />
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-sm font-medium text-gray-900">
                                            {entry.description}
                                        </div>
                                        <div className="text-xs text-gray-500">
                                            {formatDate(entry.date)} ·{' '}
                                            {SOURCE_LABELS[entry.source]}
                                        </div>
                                    </div>
                                    <div
                                        className={`whitespace-nowrap text-sm font-semibold ${
                                            entry.type === 'income'
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        }`}
                                    >
                                        {entry.type === 'income' ? '+' : '−'}
                                        {formatCurrency(entry.amount)}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <p className="mt-3 text-xs text-gray-400">
                {transactions.length}{' '}
                {transactions.length === 1 ? 'entry' : 'entries'}. Contracts
                show their most recent recorded payment only; recurring monthly
                income is not listed.
            </p>
        </div>
    );
}
