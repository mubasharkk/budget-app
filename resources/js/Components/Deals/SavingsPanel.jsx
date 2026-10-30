import Panel, { EmptyState } from '@/Components/Deals/Panel';
import ProductLink from '@/Components/Deals/ProductLink';
import { formatCurrency } from '@/utils/money';

export default function SavingsPanel({ rows }) {
    return (
        <Panel title="Potential savings">
            {rows.length === 0 ? (
                <EmptyState>
                    No overpayments detected yet. Upload more receipts from different vendors to
                    compare prices.
                </EmptyState>
            ) : (
                <ul className="divide-y divide-gray-100">
                    {rows.map((row) => (
                        <li key={row.receipt_item_id} className="flex items-start justify-between gap-4 py-3">
                            <div className="min-w-0">
                                <ProductLink productId={row.product_id}>{row.product_name}</ProductLink>
                                <p className="mt-0.5 text-xs text-gray-500">
                                    Paid {formatCurrency(row.paid_price)} at {row.vendor} · cheapest{' '}
                                    {formatCurrency(row.cheapest_price)}
                                    {row.cheapest_vendor ? ` at ${row.cheapest_vendor}` : ''}
                                </p>
                            </div>
                            <div className="shrink-0 text-right">
                                <div className="text-sm font-semibold text-green-700">
                                    +{formatCurrency(row.potential_savings)}
                                </div>
                                <div className="text-xs text-gray-400">×{row.quantity}</div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}
