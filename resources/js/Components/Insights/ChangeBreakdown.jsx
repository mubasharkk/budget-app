import { Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/money';
import { changeTone, formatSigned } from './insightFormat';

const EFFECTS = [
    { key: 'amount', up: 'Bought more', down: 'Bought less' },
    { key: 'price', up: 'Paid more per unit', down: 'Paid less per unit' },
    { key: 'new', up: 'Bought only this period', down: 'Bought only this period' },
    { key: 'stopped', up: 'Bought only before', down: 'Bought only before' },
    { key: 'unclear', up: 'Cannot tell why', down: 'Cannot tell why' },
];

function EffectBar({ value, largest }) {
    const width = `${(Math.abs(value) / largest) * 50}%`;

    return (
        <div className="relative h-3" aria-hidden="true">
            <div
                className={`absolute inset-y-0 ${value > 0 ? 'left-1/2 rounded-r bg-red-500' : 'right-1/2 rounded-l bg-green-600'}`}
                style={{ width }}
            />
            <div className="absolute -inset-y-1.5 left-1/2 w-px bg-gray-300" />
        </div>
    );
}

/**
 * Why spend moved: the parts add up to the change, followed by the products
 * that moved it most.
 */
export default function ChangeBreakdown({ changes, change }) {
    const { effects, movers } = changes;
    const rows = effects ? EFFECTS.filter(({ key }) => effects[key] !== 0) : [];
    const largest = Math.max(...rows.map(({ key }) => Math.abs(effects[key])), 0.01);

    return (
        <section className="bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
            <h3 className="text-base font-medium text-gray-900">What changed, and why</h3>

            {!effects ? (
                <p className="py-8 text-center text-sm text-gray-600">
                    Nothing was recorded in the earlier period, so there is nothing to compare with yet.
                </p>
            ) : rows.length === 0 ? (
                <p className="py-8 text-center text-sm text-gray-600">Nothing changed between the two periods.</p>
            ) : (
                <div className="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-5 lg:gap-10">
                    <dl className="space-y-3 lg:col-span-2">
                        {rows.map(({ key, up, down }) => (
                            <div key={key}>
                                <div className="flex justify-between gap-3 text-sm">
                                    <dt className="text-gray-700">{effects[key] > 0 ? up : down}</dt>
                                    <dd className="font-medium tabular-nums text-gray-900">{formatSigned(effects[key])}</dd>
                                </div>
                                <div className="mt-1">
                                    <EffectBar value={effects[key]} largest={largest} />
                                </div>
                            </div>
                        ))}
                        <div className="flex justify-between gap-3 border-t border-gray-200 pt-3 text-sm font-semibold">
                            <dt className="text-gray-900">Total change</dt>
                            <dd className={`tabular-nums ${changeTone(change)}`}>{formatSigned(change)}</dd>
                        </div>
                    </dl>

                    <ul className="min-w-0 divide-y divide-gray-100 lg:col-span-3">
                        {movers.map((item) => (
                            <li key={item.key} className="flex items-start justify-between gap-3 py-2">
                                <div className="min-w-0">
                                    <div className="truncate text-sm font-medium text-gray-900">
                                        {item.url ? (
                                            <Link href={item.url} className="hover:text-indigo-700 hover:underline">
                                                {item.label}
                                            </Link>
                                        ) : (
                                            item.label
                                        )}
                                    </div>
                                    <div className="text-xs text-gray-600">
                                        {item.reason}
                                        {item.kind === 'price' &&
                                            ` (${formatCurrency(item.previous_unit_price)} → ${formatCurrency(item.unit_price)})`}
                                    </div>
                                </div>
                                <span className={`shrink-0 text-sm font-semibold tabular-nums ${changeTone(item.change)}`}>
                                    {formatSigned(item.change)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}
