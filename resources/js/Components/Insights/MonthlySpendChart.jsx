import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import Checkbox from '@/Components/Checkbox';
import { formatCurrency, getDefaultCurrency, localeForCurrency } from '@/utils/money';

/**
 * Validated for colour-blind separation in this order, so keep the order.
 * A category outside the eight biggest is grey rather than a repeated colour.
 */
const COLOURS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
const OTHER_COLOUR = '#9CA3AF';

const colourOf = (row) => (row.colour === null ? OTHER_COLOUR : COLOURS[row.colour]);
const sum = (values) => values.reduce((total, value) => total + value, 0);

function MonthTooltip({ active, payload, label }) {
    const rows = (payload ?? []).filter((row) => row.value).sort((a, b) => b.value - a.value);

    if (!active || rows.length === 0) {
        return null;
    }

    return (
        <div className="rounded-md border border-gray-200 bg-white p-3 text-xs shadow-sm">
            <div className="mb-1 flex justify-between gap-6 font-semibold text-gray-900">
                <span>{label}</span>
                <span className="tabular-nums">{formatCurrency(sum(rows.map((row) => row.value)))}</span>
            </div>
            {rows.map((row) => (
                <div key={row.dataKey} className="flex justify-between gap-6 text-gray-700">
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-sm" style={{ backgroundColor: row.color }} />
                        {row.name}
                    </span>
                    <span className="tabular-nums">{formatCurrency(row.value)}</span>
                </div>
            ))}
        </div>
    );
}

/**
 * Twelve months of consumption by category, with the chosen period at full
 * strength. Categories switch on and off, contracts can be added, and
 * choosing a month shows it in the whole page.
 */
export default function MonthlySpendChart({ chart, onSelectMonth }) {
    const [hidden, setHidden] = useState([]);
    const [withContracts, setWithContracts] = useState(false);
    const { months, series } = chart;

    const valueAt = (row, index) => row.variable[index] + (withContracts ? row.fixed[index] : 0);
    const available = series.filter((row) => months.some((month, index) => valueAt(row, index) !== 0));
    const visible = available.filter((row) => !hidden.includes(row.key));
    const totals = months.map((month, index) => sum(visible.map((row) => valueAt(row, index))));
    const hasContracts = series.some((row) => row.fixed.some((value) => value !== 0));

    const data = months.map((month, index) => ({
        label: month.label,
        ...Object.fromEntries(visible.map((row) => [row.key, valueAt(row, index)])),
    }));

    const axisFormat = new Intl.NumberFormat(localeForCurrency(getDefaultCurrency()), {
        style: 'currency',
        currency: getDefaultCurrency(),
        maximumFractionDigits: 0,
    });

    const toggle = (key) =>
        setHidden((current) => (current.includes(key) ? current.filter((value) => value !== key) : [...current, key]));

    const selectFromChart = (state) => {
        const month = months[Number(state?.activeTooltipIndex ?? NaN)];
        if (month) {
            onSelectMonth(month);
        }
    };

    return (
        <section className="min-w-0 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-base font-medium text-gray-900">Monthly spend</h3>
                    <p className="text-sm text-gray-600">
                        {formatCurrency(sum(totals))} over 12 months.
                        {available.length > 0 && ' Click a month to see it in detail.'}
                    </p>
                </div>
                {hasContracts && (
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <Checkbox checked={withContracts} onChange={(e) => setWithContracts(e.target.checked)} />
                        Add contracts
                    </label>
                )}
            </div>

            {available.length === 0 ? (
                <p className="py-12 text-center text-sm text-gray-600">Nothing recorded in these months.</p>
            ) : (
                <>
                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        {available.map((row) => {
                            const on = !hidden.includes(row.key);
                            return (
                                <button
                                    key={row.key}
                                    type="button"
                                    onClick={() => toggle(row.key)}
                                    aria-pressed={on}
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${
                                        on
                                            ? 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                                            : 'border-dashed border-gray-300 bg-gray-50 text-gray-500 line-through'
                                    }`}
                                >
                                    <span
                                        className="h-2.5 w-2.5 rounded-full"
                                        style={{ backgroundColor: on ? colourOf(row) : '#D1D5DB' }}
                                    />
                                    {row.label}
                                </button>
                            );
                        })}
                        <button
                            type="button"
                            onClick={() => setHidden([])}
                            className="px-1 text-xs font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            All
                        </button>
                        <button
                            type="button"
                            onClick={() => setHidden(available.map((row) => row.key))}
                            className="px-1 text-xs font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            None
                        </button>
                    </div>

                    <div className="mt-4 cursor-pointer">
                        <ResponsiveContainer width="100%" height={280}>
                            <BarChart data={data} onClick={selectFromChart} margin={{ top: 4, right: 0, left: 0, bottom: 0 }}>
                                <CartesianGrid stroke="#F3F4F6" vertical={false} />
                                <XAxis dataKey="label" tickLine={false} axisLine={{ stroke: '#E5E7EB' }} tick={{ fontSize: 11, fill: '#4B5563' }} />
                                <YAxis
                                    width={56}
                                    tickLine={false}
                                    axisLine={false}
                                    tick={{ fontSize: 11, fill: '#4B5563' }}
                                    tickFormatter={(value) => axisFormat.format(value)}
                                />
                                <Tooltip content={<MonthTooltip />} cursor={{ fill: '#F9FAFB' }} />
                                {visible.map((row, position) => (
                                    <Bar
                                        key={`${row.key}-${position}`}
                                        dataKey={row.key}
                                        name={row.label}
                                        stackId="spend"
                                        fill={colourOf(row)}
                                        stroke="#FFFFFF"
                                        strokeWidth={1}
                                        maxBarSize={40}
                                        radius={position === visible.length - 1 ? [4, 4, 0, 0] : 0}
                                        isAnimationActive={false}
                                    >
                                        {months.map((month) => (
                                            <Cell key={month.start} fillOpacity={month.in_period ? 1 : 0.35} />
                                        ))}
                                    </Bar>
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                    </div>

                    <details className="mt-2 text-sm">
                        <summary className="cursor-pointer text-xs font-medium text-indigo-600">Show as a table</summary>
                        <div className="mt-2 overflow-x-auto">
                            <table className="min-w-full text-xs">
                                <thead>
                                    <tr className="text-gray-600">
                                        <th className="py-1 pr-3 text-left font-medium">Month</th>
                                        {visible.map((row) => (
                                            <th key={row.key} className="px-2 py-1 text-right font-medium">
                                                {row.label}
                                            </th>
                                        ))}
                                        <th className="py-1 pl-2 text-right font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 tabular-nums text-gray-900">
                                    {months.map((month, index) => (
                                        <tr key={month.start}>
                                            <th className="whitespace-nowrap py-1 pr-3 text-left font-normal">
                                                <button
                                                    type="button"
                                                    onClick={() => onSelectMonth(month)}
                                                    className="text-indigo-600 hover:underline"
                                                >
                                                    {month.label}
                                                </button>
                                            </th>
                                            {visible.map((row) => (
                                                <td key={row.key} className="whitespace-nowrap px-2 py-1 text-right">
                                                    {formatCurrency(valueAt(row, index))}
                                                </td>
                                            ))}
                                            <td className="whitespace-nowrap py-1 pl-2 text-right font-semibold">
                                                {formatCurrency(totals[index])}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </details>
                </>
            )}
        </section>
    );
}
