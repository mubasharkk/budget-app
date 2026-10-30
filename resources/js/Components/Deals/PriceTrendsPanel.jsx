import Panel, { EmptyState } from '@/Components/Deals/Panel';
import ProductLink from '@/Components/Deals/ProductLink';
import TrendBadge from '@/Components/Deals/TrendBadge';
import { formatCurrency } from '@/utils/money';

const formatChange = (percent) => (percent > 0 ? `+${percent}%` : `${percent}%`);

export default function PriceTrendsPanel({ rows }) {
    return (
        <Panel title="Recent price trends">
            {rows.length === 0 ? (
                <EmptyState>Need at least two price observations per product to show trends.</EmptyState>
            ) : (
                <ul className="divide-y divide-gray-100">
                    {rows.map((row) => (
                        <li key={row.product_id} className="flex items-center justify-between py-2.5">
                            <div>
                                <ProductLink productId={row.product_id}>{row.product_name}</ProductLink>
                                <div className="mt-0.5 text-xs text-gray-500">
                                    {formatCurrency(row.previous_price)} → {formatCurrency(row.latest_price)}
                                    {row.change_percent != null && ` (${formatChange(row.change_percent)})`}
                                </div>
                            </div>
                            <TrendBadge direction={row.direction} />
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
