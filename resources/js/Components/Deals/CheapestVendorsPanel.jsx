import Panel, { EmptyState } from '@/Components/Deals/Panel';
import ProductLink from '@/Components/Deals/ProductLink';
import { formatCurrency } from '@/utils/money';

export default function CheapestVendorsPanel({ rows }) {
    return (
        <Panel title="Cheapest vendor per product">
            {rows.length === 0 ? (
                <EmptyState>No product prices recorded yet.</EmptyState>
            ) : (
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-500">
                            <th className="pb-2 font-medium">Product</th>
                            <th className="pb-2 font-medium">Vendor</th>
                            <th className="pb-2 text-right font-medium">Best price</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.map((row) => (
                            <tr key={row.product_id}>
                                <td className="py-2">
                                    <ProductLink productId={row.product_id} className="text-sm">
                                        {row.product_name}
                                    </ProductLink>
                                </td>
                                <td className="py-2 text-gray-600">{row.cheapest_vendor ?? '—'}</td>
                                <td className="py-2 text-right font-medium text-gray-900">
                                    {formatCurrency(row.cheapest_price)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Panel>
    );
}
