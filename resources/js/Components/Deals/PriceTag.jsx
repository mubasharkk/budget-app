import { formatCurrency } from '@/utils/money';
import { formatUnitPrice } from '@/utils/deals';

/**
 * Pack price with the unit price beside it; with `unitFirst` the unit price (the
 * fair comparison across pack sizes) is the prominent figure.
 */
export default function PriceTag({ offer, size = 'md', showUnitPrice = true, unitFirst = false }) {
    const unitPrice = showUnitPrice ? formatUnitPrice(offer) : null;
    const leadWithUnit = unitFirst && unitPrice;
    const priceClass = size === 'lg' ? 'text-lg' : 'text-sm';

    return (
        <span className="inline-flex flex-wrap items-baseline gap-x-2">
            <span className={`${priceClass} font-semibold text-gray-900`}>
                {leadWithUnit ? unitPrice : formatCurrency(offer.price)}
            </span>
            {offer.old_price && !leadWithUnit && (
                <s className="text-xs text-gray-400">{formatCurrency(offer.old_price)}</s>
            )}
            {leadWithUnit ? (
                <span className="text-xs text-gray-500">{formatCurrency(offer.price)}</span>
            ) : (
                unitPrice && <span className="text-xs text-gray-500">{unitPrice}</span>
            )}
        </span>
    );
}
