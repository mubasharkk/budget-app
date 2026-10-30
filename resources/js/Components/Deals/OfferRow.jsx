import DiscountBadge from '@/Components/Deals/DiscountBadge';
import EndsBadge from '@/Components/Deals/EndsBadge';
import PriceTag from '@/Components/Deals/PriceTag';
import RetailerBadge from '@/Components/Deals/RetailerBadge';

export default function OfferRow({ offer, unitFirst = false }) {
    return (
        <li className="grid grid-cols-[5.5rem_minmax(0,1fr)_auto] items-center gap-3 px-4 py-3">
            <RetailerBadge label={offer.retailer_label} />
            <div className="min-w-0">
                <p className="truncate text-sm text-gray-900">
                    {offer.brand && <span className="text-gray-500">{offer.brand} · </span>}
                    {offer.title}
                </p>
                <div className="mt-1 flex flex-wrap gap-1">
                    <DiscountBadge percent={offer.discount_percent} />
                    <EndsBadge validTo={offer.valid_to} />
                </div>
            </div>
            <div className="text-right">
                <PriceTag offer={offer} unitFirst={unitFirst} />
            </div>
        </li>
    );
}
