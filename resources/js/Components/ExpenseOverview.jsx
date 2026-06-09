import React, { useEffect, useState } from 'react';
import axios from 'axios';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    Legend,
    PieChart,
    Pie,
    Cell,
    ResponsiveContainer,
} from 'recharts';
import { formatCurrency } from '@/utils/money';

const FIXED_COLOR = '#6366F1';
const VARIABLE_COLOR = '#10B981';

function SummaryCard({ label, value, accent = 'text-gray-900', children }) {
    return (
        <div className="rounded-lg bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className={`mt-1 text-2xl font-semibold ${accent}`}>
                {value}
            </div>
            {children}
        </div>
    );
}

function Skeleton() {
    return (
        <div className="animate-pulse space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {[0, 1, 2].map((i) => (
                    <div key={i} className="h-24 rounded-lg bg-gray-200" />
                ))}
            </div>
            <div className="h-72 rounded-lg bg-gray-200" />
        </div>
    );
}

export default function ExpenseOverview() {
    const [period, setPeriod] = useState('month');
    const [overview, setOverview] = useState(null);
    const [trend, setTrend] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        Promise.all([
            axios.get('/dashboard/overview', { params: { period } }),
            axios.get('/dashboard/trend', { params: { period } }),
        ])
            .then(([overviewRes, trendRes]) => {
                if (cancelled) return;
                setOverview(overviewRes.data);
                setTrend(trendRes.data.trend);
            })
            .catch((error) => {
                console.error('Error fetching expense overview:', error);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [period]);

    const periodLabel = period === 'week' ? 'This week' : 'This month';

    const renderDelta = () => {
        if (!overview || overview.delta_percent === null) return null;
        const up = overview.delta >= 0;
        return (
            <div
                className={`mt-1 text-xs font-medium ${up ? 'text-red-600' : 'text-green-600'}`}
            >
                {up ? '▲' : '▼'} {formatCurrency(Math.abs(overview.delta))} (
                {Math.abs(overview.delta_percent)}%) vs previous{' '}
                {period === 'week' ? 'week' : 'month'}
            </div>
        );
    };

    const donutData = overview
        ? [
              { name: 'Fixed', value: overview.current.fixed },
              { name: 'Variable', value: overview.current.variable },
          ].filter((d) => d.value > 0)
        : [];

    const categories = overview?.current.by_category ?? [];
    const maxCategory = categories.reduce(
        (max, c) => Math.max(max, c.total),
        0,
    );

    return (
        <div className="space-y-6 p-6">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium text-gray-900">
                    Expense Overview
                </h3>
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

            {loading || !overview ? (
                <Skeleton />
            ) : (
                <>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <SummaryCard
                            label={`Total spend (${periodLabel.toLowerCase()})`}
                            value={formatCurrency(overview.current.total)}
                        >
                            {renderDelta()}
                        </SummaryCard>
                        <SummaryCard
                            label="Fixed (contracts)"
                            value={formatCurrency(overview.current.fixed)}
                            accent="text-indigo-600"
                        />
                        <SummaryCard
                            label="Variable (receipts)"
                            value={formatCurrency(overview.current.variable)}
                            accent="text-emerald-600"
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div className="rounded-lg border border-gray-100 p-4">
                            <h4 className="mb-2 text-sm font-medium text-gray-700">
                                Fixed vs variable
                            </h4>
                            {donutData.length === 0 ? (
                                <p className="py-12 text-center text-sm text-gray-400">
                                    No spending recorded for this period.
                                </p>
                            ) : (
                                <ResponsiveContainer width="100%" height={240}>
                                    <PieChart>
                                        <Pie
                                            data={donutData}
                                            dataKey="value"
                                            nameKey="name"
                                            innerRadius={60}
                                            outerRadius={90}
                                            paddingAngle={2}
                                        >
                                            <Cell fill={FIXED_COLOR} />
                                            <Cell fill={VARIABLE_COLOR} />
                                        </Pie>
                                        <Tooltip
                                            formatter={(value) =>
                                                formatCurrency(value)
                                            }
                                        />
                                        <Legend />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        <div className="rounded-lg border border-gray-100 p-4">
                            <h4 className="mb-3 text-sm font-medium text-gray-700">
                                By category
                            </h4>
                            {categories.length === 0 ? (
                                <p className="py-12 text-center text-sm text-gray-400">
                                    No categorized spending yet.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {categories.slice(0, 6).map((c) => (
                                        <li key={c.category}>
                                            <div className="flex justify-between text-sm">
                                                <span className="text-gray-700">
                                                    {c.category}
                                                </span>
                                                <span className="font-medium text-gray-900">
                                                    {formatCurrency(c.total)}
                                                </span>
                                            </div>
                                            <div className="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                                <div
                                                    className="h-full bg-gray-700"
                                                    style={{
                                                        width: `${maxCategory ? (c.total / maxCategory) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border border-gray-100 p-4">
                        <h4 className="mb-3 text-sm font-medium text-gray-700">
                            Trend
                        </h4>
                        <ResponsiveContainer width="100%" height={260}>
                            <BarChart data={trend}>
                                <XAxis
                                    dataKey="label"
                                    tick={{ fontSize: 12 }}
                                />
                                <YAxis tick={{ fontSize: 12 }} />
                                <Tooltip
                                    formatter={(value) => formatCurrency(value)}
                                />
                                <Legend />
                                <Bar
                                    dataKey="fixed"
                                    stackId="spend"
                                    name="Fixed"
                                    fill={FIXED_COLOR}
                                />
                                <Bar
                                    dataKey="variable"
                                    stackId="spend"
                                    name="Variable"
                                    fill={VARIABLE_COLOR}
                                />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </>
            )}
        </div>
    );
}
