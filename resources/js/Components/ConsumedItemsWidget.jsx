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
            {[0, 1, 2, 3, 4].map((i) => (
                <div key={i} className="h-10 animate-pulse rounded bg-gray-100" />
            ))}
        </div>
    );
}

function formatPeriodRange(start, end) {
    if (!start || !end) {
        return '';
    }

    const fmt = (value) =>
        new Date(value).toLocaleDateString('de-DE', {
            month: 'short',
            day: 'numeric',
        });

    return `${fmt(start)} – ${fmt(end)}`;
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
            .get('/dashboard/consumption', { params })
            .then((res) => {
                if (!cancelled) {
                    setData(res.data);
                }
            })
            .catch((error) =>
                console.error('Error fetching consumed items:', error),
            )
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [period, limit, categoryId, metric]);

    const items = (data?.items ?? []).map((row) => ({
        ...row,
        total_quantity: Number(row.total_quantity),
        total_spend: Number(row.total_spend),
        purchase_count: Number(row.purchase_count),
    }));

    const periodLabel =
        PERIODS.find((p) => p.key === period)?.label ?? 'Month';

    return (
        <div className="p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">
                        Items consumed
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Top purchases for the selected {periodLabel.toLowerCase()}
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
                                period === p.key
                                    ? 'bg-gray-800 text-white'
                                    : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>

                <div>
                    <label
                        htmlFor="consumed-limit"
                        className="sr-only"
                    >
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
                    <label htmlFor="consumed-metric" className="sr-only">
                        Sort by
                    </label>
                    <select
                        id="consumed-metric"
                        className={selectClasses}
                        value={metric}
                        onChange={(e) => setMetric(e.target.value)}
                    >
                        <option value="quantity">By quantity</option>
                        <option value="spend">By spend</option>
                    </select>
                </div>
            </div>

            <div className="mt-5">
                {loading ? (
                    <Skeleton />
                ) : items.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-200 py-12 text-center text-sm text-gray-500">
                        No receipt items found for this period
                        {categoryId ? ' and category' : ''}. Upload receipts to
                        see what you consume most.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr className="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <th className="px-3 py-2">#</th>
                                    <th className="px-3 py-2">Item</th>
                                    <th className="px-3 py-2">Category</th>
                                    <th className="px-3 py-2 text-right">
                                        Qty
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Spend
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Purchases
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 text-sm text-gray-700">
                                {items.map((item, index) => (
                                    <tr key={`${item.item_name}-${index}`}>
                                        <td className="px-3 py-3 text-gray-400">
                                            {index + 1}
                                        </td>
                                        <td className="px-3 py-3 font-medium text-gray-900">
                                            {item.item_name}
                                        </td>
                                        <td className="px-3 py-3 text-gray-500">
                                            {item.category_name ?? '—'}
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums">
                                            {item.total_quantity}
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums">
                                            {formatCurrency(item.total_spend)}
                                        </td>
                                        <td className="px-3 py-3 text-right tabular-nums text-gray-500">
                                            {item.purchase_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
