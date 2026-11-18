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

                return (
                    <li key={item.key} className="flex items-start justify-between gap-4 py-2.5">
                        <div className="min-w-0">
                            <div className="truncate text-sm font-medium text-gray-900">
                                {item.item_name}
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
