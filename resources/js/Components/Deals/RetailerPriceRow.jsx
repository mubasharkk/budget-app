import OfferTitle from '@/Components/Deals/OfferTitle';
import RetailerBadge from '@/Components/Deals/RetailerBadge';
import { formatCurrency } from '@/utils/money';
import { formatUnitPrice } from '@/utils/deals';

/**
 * One retailer's offer in a comparison; the compared price is the prominent one.
 */
export default function RetailerPriceRow({ offer, cheapest, byUnitPrice }) {
    const main = byUnitPrice ? formatUnitPrice(offer) : formatCurrency(offer.price);
    const secondary = byUnitPrice ? formatCurrency(offer.price) : formatUnitPrice(offer);

    return (
        <li
            className={`grid grid-cols-[5.5rem_minmax(0,1fr)_auto] items-center gap-2 rounded px-2 py-1.5 ${
                cheapest ? 'bg-green-50' : ''
            }`}
        >
            <RetailerBadge label={offer.retailer_label} />
            <span className="flex min-w-0 text-xs text-gray-600">
                <OfferTitle offer={offer} className="truncate" />
            </span>
            <span className="text-right">
                <span className={`block text-sm font-semibold ${cheapest ? 'text-green-700' : 'text-gray-900'}`}>
                    {main}
                </span>
                {secondary && <span className="block text-xs text-gray-500">{secondary}</span>}
            </span>
        </li>
    );
}
