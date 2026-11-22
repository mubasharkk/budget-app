import { useState } from 'react';
import { Link } from '@inertiajs/react';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/utils/money';
import { changeTone, formatSigned, unitName } from './insightFormat';

const SHOWN = 10;

const SORTS = {
    spend: { label: 'Most spent on', compare: (a, b) => b.spend - a.spend },
    purchases: { label: 'Most often bought', compare: (a, b) => b.purchases - a.purchases || b.spend - a.spend },
    rise: { label: 'Biggest increase', compare: (a, b) => (b.change ?? 0) - (a.change ?? 0) },
    fall: { label: 'Biggest decrease', compare: (a, b) => (a.change ?? 0) - (b.change ?? 0) },
};

/**
 * Everything consumed in the period: how much, at what price, and its change.
 */
export default function ConsumedItemsTable({ items }) {
    const [sort, setSort] = useState('spend');
    const [search, setSearch] = useState('');
    const [showAll, setShowAll] = useState(false);

    const term = search.trim().toLowerCase();
    const matching = items
        .filter((item) => [item.label, ...item.products].some((name) => name.toLowerCase().includes(term)))
        .sort(SORTS[sort].compare);
    const shown = showAll || term ? matching : matching.slice(0, SHOWN);

    return (
        <section className="min-w-0 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-base font-medium text-gray-900">What you consume</h3>
                <div className="flex min-w-0 flex-wrap gap-2">
                    <TextInput
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Find a product"
                        aria-label="Find a product"
                        className="w-40 text-sm"
                    />
                    <select
                        value={sort}
                        onChange={(e) => setSort(e.target.value)}
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        aria-label="Sort products"
                    >
                        {Object.entries(SORTS).map(([key, option]) => (
                            <option key={key} value={key}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {shown.length === 0 ? (
                <p className="py-10 text-center text-sm text-gray-600">
                    {term ? 'No product matches.' : 'Nothing consumed in this period.'}
                </p>
            ) : (
                <table className="mt-3 w-full table-fixed text-sm">
                    <thead>
                        <tr className="text-xs text-gray-600">
                            <th className="py-2 text-left font-medium">Product</th>
                            <th className="hidden w-24 py-2 text-right font-medium sm:table-cell">Amount</th>
                            <th className="hidden w-24 py-2 text-right font-medium sm:table-cell">Per unit</th>
                            <th className="w-28 py-2 text-right font-medium">Spent</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {shown.map((item) => (
                            <tr key={item.key} className="align-top">
                                <td className="py-2.5 pr-2">
                                    <div className="truncate font-medium text-gray-900" title={item.products.join(', ') || undefined}>
                                        {item.url ? (
                                            <Link href={item.url} className="hover:text-indigo-700 hover:underline">
                                                {item.label}
                                            </Link>
                                        ) : (
                                            item.label
                                        )}
                                    </div>
                                    <div className="truncate text-xs text-gray-600">
                                        {item.amount_label && <span className="sm:hidden">{item.amount_label} · </span>}
                                        bought {item.purchases}×{item.category && ` · ${item.category}`}
                                    </div>
                                </td>
                                <td
                                    className="hidden py-2.5 text-right tabular-nums text-gray-700 sm:table-cell"
                                    title={item.unmeasured_count ? `${item.unmeasured_count} purchases without a known size` : undefined}
                                >
                                    {item.amount_label ?? '—'}
                                    {item.amount_label && item.unmeasured_count > 0 && '+'}
                                </td>
                                <td className="hidden py-2.5 text-right tabular-nums text-gray-700 sm:table-cell">
                                    {item.unit_price === null ? '—' : formatCurrency(item.unit_price)}
                                    {item.unit_price !== null && <div className="text-xs text-gray-600">per {unitName(item.unit)}</div>}
                                </td>
                                <td className="whitespace-nowrap py-2.5 text-right tabular-nums">
                                    <div className="font-semibold text-gray-900">{formatCurrency(item.spend)}</div>
                                    {item.change !== null && (
                                        <div className={`text-xs ${changeTone(item.change)}`}>
                                            {item.is_new ? 'Only this period' : formatSigned(item.change)}
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            {!term && matching.length > SHOWN && (
                <button
                    type="button"
                    onClick={() => setShowAll(!showAll)}
                    className="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    {showAll ? 'Show fewer' : `Show all ${matching.length}`}
                </button>
            )}
        </section>
    );
}
