import PriceTag from '@/Components/Deals/PriceTag';
import RetailerBadge from '@/Components/Deals/RetailerBadge';
import { formatCurrency } from '@/utils/money';

export default function ForYouListItem({ recommendation }) {
    const { offer, saving } = recommendation;

    return (
        <li className="flex items-center justify-between gap-3 py-2.5">
            <div className="min-w-0">
                <p className="truncate text-sm text-gray-900">{offer.title}</p>
                <div className="mt-0.5 flex items-center gap-2">
                    <RetailerBadge label={offer.retailer_label} />
                    {saving && (
                        <span className="text-xs text-green-700">Save {formatCurrency(saving)}</span>
                    )}
                </div>
            </div>
            <PriceTag offer={offer} showUnitPrice={false} />
        </li>
    );
}
