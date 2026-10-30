import DiscountBadge from '@/Components/Deals/DiscountBadge';
import EndsBadge from '@/Components/Deals/EndsBadge';
import OfferTitle from '@/Components/Deals/OfferTitle';
import PriceTag from '@/Components/Deals/PriceTag';
import RetailerBadge from '@/Components/Deals/RetailerBadge';
import { formatCurrency } from '@/utils/money';

export default function ForYouCard({ recommendation }) {
    const { offer, product, saving } = recommendation;

    return (
        <li className="flex flex-col gap-2 rounded-lg border border-gray-200 p-4">
            <div className="flex items-center justify-between gap-2">
                <RetailerBadge label={offer.retailer_label} />
                <EndsBadge validTo={offer.valid_to} />
                <DiscountBadge percent={offer.discount_percent} />
            </div>
            <p className="flex min-w-0 text-sm font-medium text-gray-900">
                <OfferTitle offer={offer} />
            </p>
            <PriceTag offer={offer} size="lg" />
            <p className="text-xs text-gray-500">
                You bought {product.name} {product.purchase_count}× recently
                {product.last_vendor ? `, last for ${formatCurrency(product.last_paid_price)} at ${product.last_vendor}` : ''}.
            </p>
            {saving && (
                <p className="mt-auto text-xs font-medium text-green-700">
                    Save {formatCurrency(saving)}
                </p>
            )}
        </li>
    );
}
