import { Link } from '@inertiajs/react';
import { formatCurrency } from '@/utils/money';
import { describeConsumption } from './consumedAmount';

/**
 * What the user consumed: the general name, how much and how often, the
 * receipt names merged into it, and what it cost.
 */
export default function ConsumedItemList({ items }) {
    return (
        <ul className="divide-y divide-gray-100">
            {items.map((item) => {
                const merged = item.variants.filter((name) => name !== item.item_name);
                const groupId = item.key.startsWith('group:') ? item.key.slice(6) : null;

                return (
                    <li key={item.key} className="flex items-start justify-between gap-4 py-2.5">
                        <div className="min-w-0">
                            <div className="truncate text-sm font-medium text-gray-900">
                                {groupId ? (
                                    <Link
                                        href={route('insights.groups.show', groupId)}
                                        className="hover:text-indigo-700 hover:underline"
                                    >
                                        {item.item_name}
                                    </Link>
                                ) : (
                                    item.item_name
                                )}
                            </div>
                            <div className="text-xs text-gray-600">
                                {describeConsumption(item)}
                            </div>
                            {merged.length > 0 && (
                                <div
                                    className="truncate text-xs text-gray-400"
                                    title={merged.join(', ')}
                                >
                                    {merged.join(', ')}
                                </div>
                            )}
                        </div>
                        <div className="shrink-0 font-semibold tabular-nums text-gray-900">
                            {formatCurrency(item.total_spend)}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
