import { useEffect, useState } from 'react';
import axios from 'axios';
import { Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/money';

const PERIODS = [
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Month' },
    { key: 'quarter', label: 'Quarter' },
];

const LIMITS = [10, 20, 50, 100];

const selectClasses =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

function Skeleton() {
    return (
        <div className="space-y-3">
            <div className="grid grid-cols-3 gap-3">
                {[0, 1, 2].map((i) => (
                    <div key={i} className="h-16 animate-pulse rounded-lg bg-gray-100" />
                ))}
            </div>
            {[0, 1, 2, 3, 4].map((i) => (
                <div key={i} className="h-12 animate-pulse rounded bg-gray-100" />
            ))}
        </div>
    );
}

function formatPeriodRange(start, end) {
    if (!start || !end) {
        return '';
    }

    const fmt = (value) =>
        new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        });

    return `${fmt(start)} – ${fmt(end)}`;
}

/**
 * Spending up is shown in red and down in green: for consumption, less is better.
 */
function ChangeBadge({ change, isNew }) {
    if (isNew) {
        return (
            <span className="rounded-full bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700">
                New
            </span>
        );
    }

    if (change === null || change === undefined) {
        return null;
    }

    if (Math.abs(change) < 1) {
        return <span className="text-xs text-gray-400">±0%</span>;
    }

    const up = change > 0;

    return (
        <span
            className={`text-xs font-medium tabular-nums ${up ? 'text-red-600' : 'text-green-600'}`}
            title="Compared with the same stretch of the previous period"
        >
            {up ? '▲' : '▼'} {Math.abs(change).toLocaleString(undefined, { maximumFractionDigits: 0 })}%
        </span>
    );
}

function SummaryTile({ label, children, hint }) {
    return (
        <div className="rounded-lg bg-gray-50 px-4 py-3">
            <div className="text-xs font-medium uppercase tracking-wider text-gray-500">{label}</div>
            <div className="mt-1 flex items-baseline gap-2 text-lg font-semibold tabular-nums text-gray-900">
                {children}
            </div>
            {hint && <div className="text-xs text-gray-500">{hint}</div>}
        </div>
    );
}

function ItemRow({ item, rank }) {
    const label = item.url ? (
        <Link href={item.url} className="hover:text-indigo-700 hover:underline">
            {item.label}
        </Link>
    ) : (
        item.label
    );

    const meta = [
        item.amount_label,
        `bought ${item.purchases}×`,
        item.category,
    ].filter(Boolean);

    return (
        <li className="py-3">
            <div className="flex items-start gap-3">
                <span className="w-5 shrink-0 pt-0.5 text-right text-xs tabular-nums text-gray-400">{rank}</span>
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <div className="truncate text-sm font-medium text-gray-900">{label}</div>
                            <div
                                className="truncate text-xs text-gray-500"
                                title={
                                    item.unmeasured_count > 0
                                        ? `${item.unmeasured_count} purchases without a known size`
                                        : undefined
                                }
                            >
                                {meta.join(' · ')}
                            </div>
                            {item.products.length > 0 && (
                                <div className="truncate text-xs text-gray-400" title={item.products.join(', ')}>
                                    {item.products.join(', ')}
                                </div>
                            )}
                        </div>
                        <div className="shrink-0 text-right">
                            <div className="text-sm font-semibold tabular-nums text-gray-900">
                                {formatCurrency(item.spend)}
                            </div>
                            <ChangeBadge change={item.change_percent} isNew={item.is_new} />
                        </div>
                    </div>
                    <div
                        className="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100"
                        title={`${item.share_percent}% of what you spent on consumption`}
                    >
                        <div
                            className="h-full rounded-full bg-emerald-500"
                            style={{ width: `${Math.max(item.share_percent, 1)}%` }}
                        />
                    </div>
                </div>
            </div>
        </li>
    );
}

export default function ConsumedItemsWidget() {
    const [period, setPeriod] = useState('month');
    const [limit, setLimit] = useState(10);
    const [categoryId, setCategoryId] = useState('');
    const [metric, setMetric] = useState('quantity');
    const [categories, setCategories] = useState([]);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        axios
            .get('/dashboard/categories')
            .then((res) => setCategories(res.data.categories ?? []))
            .catch(() => {});
    }, []);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        const params = { period, limit, metric };
        if (categoryId) {
            params.category_id = categoryId;
        }

        axios
            .get(route('dashboard.items-consumed'), { params })
            .then((res) => {
                if (!cancelled) {
                    setData(res.data);
                }
            })
            .catch((error) => console.error('Error fetching consumed items:', error))
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [period, limit, categoryId, metric]);

    const items = data?.items ?? [];
    const totals = data?.totals;
    const periodLabel = (PERIODS.find((p) => p.key === period)?.label ?? 'Month').toLowerCase();

    return (
        <div className="p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">Items consumed</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        What you used up this {periodLabel}, grouped by product
                        {data?.start && data?.end && (
                            <span className="ml-1 text-gray-400">
                                ({formatPeriodRange(data.start, data.end)})
                            </span>
                        )}
                    </p>
                </div>
                <Link
                    href={route('insights')}
                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    Full insights →
                </Link>
            </div>

            <div className="mt-4 flex flex-wrap items-end gap-3">
                <div className="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
                    {PERIODS.map((p) => (
                        <button
                            key={p.key}
                            type="button"
                            onClick={() => setPeriod(p.key)}
                            className={`rounded px-3 py-1 text-sm font-medium transition ${
                                period === p.key ? 'bg-gray-800 text-white' : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>

                <div>
                    <label htmlFor="consumed-metric" className="sr-only">
                        Sort by
                    </label>
                    <select
                        id="consumed-metric"
                        className={selectClasses}
                        value={metric}
                        onChange={(e) => setMetric(e.target.value)}
                    >
                        <option value="quantity">Most bought</option>
                        <option value="spend">Most spent on</option>
                    </select>
                </div>

                <div className="min-w-[10rem] flex-1 sm:max-w-xs">
                    <label htmlFor="consumed-category" className="sr-only">
                        Category
                    </label>
                    <select
                        id="consumed-category"
                        className={`${selectClasses} w-full`}
                        value={categoryId}
                        onChange={(e) => setCategoryId(e.target.value)}
                    >
                        <option value="">All categories</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label htmlFor="consumed-limit" className="sr-only">
                        Show
                    </label>
                    <select
                        id="consumed-limit"
                        className={selectClasses}
                        value={limit}
                        onChange={(e) => setLimit(Number(e.target.value))}
                    >
                        {LIMITS.map((value) => (
                            <option key={value} value={value}>
                                Top {value}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="mt-5">
                {loading ? (
                    <Skeleton />
                ) : items.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-200 py-12 text-center text-sm text-gray-500">
                        Nothing consumed this {periodLabel}
                        {categoryId ? ' in this category' : ''}. Upload receipts to see what you use up most.
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <SummaryTile
                                label="Spent"
                                hint={
                                    totals.previous_spend !== null
                                        ? `${formatCurrency(totals.previous_spend)} by this point last ${periodLabel}`
                                        : undefined
                                }
                            >
                                {formatCurrency(totals.spend)}
                                <ChangeBadge change={totals.change_percent} />
                            </SummaryTile>
                            <SummaryTile label="Products">{totals.labels}</SummaryTile>
                            <SummaryTile label="Purchases">{totals.purchases}</SummaryTile>
                        </div>

                        <ul className="mt-3 divide-y divide-gray-100">
                            {items.map((item, index) => (
                                <ItemRow key={item.key} item={item} rank={index + 1} />
                            ))}
                        </ul>

                        {totals.labels > items.length && (
                            <p className="mt-3 text-center text-xs text-gray-400">
                                Showing {items.length} of {totals.labels} products ·{' '}
                                <Link href={route('insights')} className="text-indigo-600 hover:text-indigo-800">
                                    see all on Insights
                                </Link>
                            </p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
