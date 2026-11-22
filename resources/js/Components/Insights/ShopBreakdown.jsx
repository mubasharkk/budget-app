import { formatCurrency } from '@/utils/money';

/**
 * Where the period's consumption was bought.
 */
export default function ShopBreakdown({ shops }) {
    const largest = Math.max(...shops.map((shop) => shop.total_spent), 0.01);

    return (
        <section className="min-w-0 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
            <h3 className="text-base font-medium text-gray-900">Where you shop for it</h3>
            {shops.length === 0 ? (
                <p className="py-10 text-center text-sm text-gray-600">No shops in this period.</p>
            ) : (
                <ul className="mt-4 space-y-3">
                    {shops.map((shop) => (
                        <li key={shop.vendor}>
                            <div className="flex justify-between gap-3 text-sm">
                                <span className="truncate text-gray-900">{shop.vendor}</span>
                                <span className="font-medium tabular-nums text-gray-900">{formatCurrency(shop.total_spent)}</span>
                            </div>
                            <div className="mt-1 flex items-center gap-2">
                                <div className="h-2 flex-1">
                                    <div
                                        className="h-full rounded-r bg-brand-primary"
                                        style={{ width: `${Math.max((shop.total_spent / largest) * 100, 0)}%` }}
                                    />
                                </div>
                                <span className="w-14 shrink-0 text-right text-xs text-gray-600">
                                    {shop.receipt_count} {shop.receipt_count === 1 ? 'trip' : 'trips'}
                                </span>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
