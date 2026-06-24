import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import {
    BarChart,
    Bar,
    LineChart,
    Line,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';
import { formatCurrency } from '@/utils/money';

const CURRENT_YEAR = new Date().getFullYear();
const YEAR_OPTIONS = Array.from({ length: 5 }, (_, i) => CURRENT_YEAR - i);

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const toNumber = (rows, keys) =>
    rows.map((row) => {
        const out = { ...row };
        keys.forEach((k) => (out[k] = Number(out[k])));
        return out;
    });

function Panel({ title, children }) {
    return (
        <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="mb-4 text-base font-medium text-gray-900">{title}</h3>
            {children}
        </div>
    );
}

function EmptyState({ children }) {
    return (
        <p className="py-12 text-center text-sm text-gray-400">{children}</p>
    );
}

export default function Insights() {
    const [categoryOptions, setCategoryOptions] = useState([]);
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [year, setYear] = useState(CURRENT_YEAR);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchData = (overrides = {}) => {
        setLoading(true);
        const params = { year };
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        if (categoryId) params.category_id = categoryId;
        Object.assign(params, overrides);

        axios
            .get('/dashboard/consumption', { params })
            .then((res) => setData(res.data))
            .catch((error) =>
                console.error('Error fetching consumption data:', error),
            )
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        axios
            .get('/dashboard/categories')
            .then((res) => setCategoryOptions(res.data.categories))
            .catch(() => {});
        fetchData();
    }, []);

    const clearFilters = () => {
        setStartDate('');
        setEndDate('');
        setCategoryId('');
        setYear(CURRENT_YEAR);
        // refetch with cleared params on next tick via the values directly
        setLoading(true);
        axios
            .get('/dashboard/consumption', { params: { year: CURRENT_YEAR } })
            .then((res) => setData(res.data))
            .finally(() => setLoading(false));
    };

    const topByQuantity = data ? toNumber(data.top_by_quantity, ['total_quantity']) : [];
    const topBySpend = data ? toNumber(data.top_by_spend, ['total_spend']) : [];
    const vendors = data ? toNumber(data.vendors, ['receipt_count', 'total_spent']) : [];
    const monthlyTrend = data ? toNumber(data.monthly_trend ?? [], ['total']) : [];
    const trendYear = data?.year ?? year;
    const selectedCategoryName =
        categoryOptions.find((c) => String(c.id) === String(categoryId))?.name ??
        'All categories';
    const trendTotal = monthlyTrend.reduce((sum, row) => sum + row.total, 0);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Consumption Insights
                </h2>
            }
        >
            <Head title="Insights" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Filters */}
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                            <div>
                                <InputLabel htmlFor="start" value="From" />
                                <TextInput
                                    id="start"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={startDate}
                                    onChange={(e) =>
                                        setStartDate(e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="end" value="To" />
                                <TextInput
                                    id="end"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="category"
                                    value="Category"
                                />
                                <select
                                    id="category"
                                    className={selectClasses}
                                    value={categoryId}
                                    onChange={(e) =>
                                        setCategoryId(e.target.value)
                                    }
                                >
                                    <option value="">All categories</option>
                                    {categoryOptions.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="year" value="Trend year" />
                                <select
                                    id="year"
                                    className={selectClasses}
                                    value={year}
                                    onChange={(e) => {
                                        const nextYear = Number(e.target.value);
                                        setYear(nextYear);
                                        fetchData({ year: nextYear });
                                    }}
                                >
                                    {YEAR_OPTIONS.map((y) => (
                                        <option key={y} value={y}>
                                            {y}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-end gap-2">
                                <PrimaryButton
                                    type="button"
                                    onClick={fetchData}
                                >
                                    Apply
                                </PrimaryButton>
                                <SecondaryButton
                                    type="button"
                                    onClick={clearFilters}
                                >
                                    Clear
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>

                    {loading ? (
                        <div className="h-72 animate-pulse rounded-lg bg-gray-200" />
                    ) : (
                        <>
                        <Panel
                            title={`Monthly spend — ${selectedCategoryName} (${trendYear})`}
                        >
                            {trendTotal === 0 ? (
                                <EmptyState>
                                    No spending recorded in {trendYear}.
                                </EmptyState>
                            ) : (
                                <>
                                    <div className="mb-4 text-sm text-gray-500">
                                        Total {trendYear}:{' '}
                                        <span className="font-semibold text-gray-900">
                                            {formatCurrency(trendTotal)}
                                        </span>
                                    </div>
                                    <ResponsiveContainer width="100%" height={300}>
                                        <LineChart data={monthlyTrend}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis
                                                dataKey="label"
                                                tick={{ fontSize: 12 }}
                                            />
                                            <YAxis
                                                tick={{ fontSize: 12 }}
                                                tickFormatter={(v) =>
                                                    formatCurrency(v)
                                                }
                                                width={80}
                                            />
                                            <Tooltip
                                                formatter={(value) =>
                                                    formatCurrency(value)
                                                }
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="total"
                                                name="Spend"
                                                stroke="#6366F1"
                                                strokeWidth={2}
                                                dot={{ r: 3 }}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </>
                            )}
                        </Panel>

                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Panel title="Most consumed (by quantity)">
                                {topByQuantity.length === 0 ? (
                                    <EmptyState>
                                        No items in this range.
                                    </EmptyState>
                                ) : (
                                    <ResponsiveContainer
                                        width="100%"
                                        height={320}
                                    >
                                        <BarChart
                                            layout="vertical"
                                            data={topByQuantity}
                                            margin={{ left: 30 }}
                                        >
                                            <XAxis
                                                type="number"
                                                tick={{ fontSize: 12 }}
                                            />
                                            <YAxis
                                                type="category"
                                                dataKey="item_name"
                                                width={120}
                                                tick={{ fontSize: 12 }}
                                            />
                                            <Tooltip />
                                            <Bar
                                                dataKey="total_quantity"
                                                name="Quantity"
                                                fill="#3B82F6"
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                )}
                            </Panel>

                            <Panel title="Top spend (by item)">
                                {topBySpend.length === 0 ? (
                                    <EmptyState>
                                        No items in this range.
                                    </EmptyState>
                                ) : (
                                    <ul className="divide-y divide-gray-100">
                                        {topBySpend.map((item) => (
                                            <li
                                                key={`${item.item_name}-${item.category_name}`}
                                                className="flex items-center justify-between py-2.5"
                                            >
                                                <div className="min-w-0">
                                                    <div className="truncate text-sm font-medium text-gray-900">
                                                        {item.item_name}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        {item.category_name ??
                                                            'Uncategorized'}{' '}
                                                        · ×{item.purchase_count}
                                                    </div>
                                                </div>
                                                <div className="font-semibold text-gray-900">
                                                    {formatCurrency(
                                                        item.total_spend,
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>

                            <Panel title="Vendor leaderboard">
                                {vendors.length === 0 ? (
                                    <EmptyState>No vendors yet.</EmptyState>
                                ) : (
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-gray-500">
                                                <th className="pb-2 font-medium">
                                                    Vendor
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Receipts
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Total spent
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {vendors.map((v) => (
                                                <tr key={v.vendor}>
                                                    <td className="py-2 text-gray-900">
                                                        {v.vendor}
                                                    </td>
                                                    <td className="py-2 text-right text-gray-600">
                                                        {v.receipt_count}
                                                    </td>
                                                    <td className="py-2 text-right font-medium text-gray-900">
                                                        {formatCurrency(
                                                            v.total_spent,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </Panel>
                        </div>
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
