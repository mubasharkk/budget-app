import { useEffect, useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatCurrency } from '@/utils/money';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const COLORS = [
    '#10B981', '#6366F1', '#F59E0B', '#EC4899', '#0EA5E9',
    '#8B5CF6', '#14B8A6', '#EF4444', '#84CC16', '#F97316',
    '#64748B', '#A855F7',
];

const sum = (values) => values.reduce((total, value) => total + value, 0);

/**
 * Spend per month of the year, stacked by category (receipts plus contracts).
 * Each category can be switched on or off; the totals follow what is shown.
 */
export default function MonthlySpendChart({ series, year, years, onYearChange }) {
    const [hidden, setHidden] = useState([]);
    const colorOf = useMemo(
        () => Object.fromEntries(series.map((row, index) => [row.key, COLORS[index % COLORS.length]])),
        [series],
    );

    useEffect(() => setHidden((current) => current.filter((key) => series.some((row) => row.key === key))), [series]);

    const visible = series.filter((row) => !hidden.includes(row.key));
    const data = MONTHS.map((label, index) => {
        const row = { label };
        visible.forEach((serie) => {
            row[serie.key] = serie.variable[index + 1] + serie.fixed[index + 1];
        });
        return row;
    });

    const receipts = sum(visible.map((row) => sum(Object.values(row.variable))));
    const contracts = sum(visible.map((row) => sum(Object.values(row.fixed))));

    const toggle = (key) =>
        setHidden((current) => (current.includes(key) ? current.filter((value) => value !== key) : [...current, key]));

    return (
        <section className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 className="text-base font-medium text-gray-900">Monthly spend</h3>
                    {series.length > 0 && (
                        <div className="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-500">
                            <span>
                                Total{' '}
                                <span className="font-semibold tabular-nums text-gray-900">
                                    {formatCurrency(receipts + contracts)}
                                </span>
                            </span>
                            <span>
                                Receipts{' '}
                                <span className="font-semibold tabular-nums text-gray-900">{formatCurrency(receipts)}</span>
                            </span>
                            <span>
                                Contracts{' '}
                                <span className="font-semibold tabular-nums text-gray-900">{formatCurrency(contracts)}</span>
                            </span>
                        </div>
                    )}
                </div>
                <select
                    value={year}
                    onChange={(e) => onYearChange(Number(e.target.value))}
                    className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    aria-label="Year"
                >
                    {years.map((value) => (
                        <option key={value} value={value}>
                            {value}
                        </option>
                    ))}
                </select>
            </div>

            {series.length === 0 ? (
                <p className="py-12 text-center text-sm text-gray-400">No spending recorded in {year}.</p>
            ) : (
                <>
                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        {series.map((row) => {
                            const on = !hidden.includes(row.key);
                            return (
                                <button
                                    key={row.key}
                                    type="button"
                                    onClick={() => toggle(row.key)}
                                    aria-pressed={on}
                                    title={`${formatCurrency(row.total)} in ${year}`}
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition ${
                                        on
                                            ? 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                                            : 'border-dashed border-gray-200 bg-gray-50 text-gray-400 line-through'
                                    }`}
                                >
                                    <span
                                        className="h-2.5 w-2.5 rounded-full"
                                        style={{ backgroundColor: on ? colorOf[row.key] : '#D1D5DB' }}
                                    />
                                    {row.label}
                                </button>
                            );
                        })}
                        <span className="mx-1 h-4 w-px bg-gray-200" />
                        <button
                            type="button"
                            onClick={() => setHidden([])}
                            className="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            All
                        </button>
                        <button
                            type="button"
                            onClick={() => setHidden(series.map((row) => row.key))}
                            className="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            None
                        </button>
                    </div>

                    <div className="mt-4">
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={data}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                                <YAxis tick={{ fontSize: 12 }} tickFormatter={(value) => formatCurrency(value)} width={80} />
                                <Tooltip
                                    formatter={(value, name) => [formatCurrency(value), name]}
                                    itemSorter={(item) => -item.value}
                                />
                                {visible.map((row) => (
                                    <Bar key={row.key} dataKey={row.key} name={row.label} stackId="spend" fill={colorOf[row.key]} />
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </>
            )}
        </section>
    );
}
