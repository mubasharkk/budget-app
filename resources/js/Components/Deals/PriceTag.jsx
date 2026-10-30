import { formatCurrency } from '@/utils/money';
import { formatUnitPrice } from '@/utils/deals';

export default function PriceTag({ offer, size = 'md', showUnitPrice = true }) {
    const unitPrice = showUnitPrice ? formatUnitPrice(offer) : null;
    const priceClass = size === 'lg' ? 'text-lg' : 'text-sm';

    return (
        <span className="inline-flex flex-wrap items-baseline gap-x-2">
            <span className={`${priceClass} font-semibold text-gray-900`}>
                {formatCurrency(offer.price)}
            </span>
            {offer.old_price && (
                <s className="text-xs text-gray-400">{formatCurrency(offer.old_price)}</s>
            )}
            {unitPrice && <span className="text-xs text-gray-500">{unitPrice}</span>}
        </span>
    );
}
