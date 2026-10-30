import RetailerPriceRow from '@/Components/Deals/RetailerPriceRow';
import { formatCurrency } from '@/utils/money';

export default function CompareCard({ comparison }) {
    const { product, offers, compared_by: comparedBy, price_gap: priceGap } = comparison;
    const byUnitPrice = comparedBy === 'unit_price';

    return (
        <li className="flex flex-col gap-3 rounded-lg border border-gray-200 p-4">
            <div>
                <p className="text-sm font-medium text-gray-900">{product.name}</p>
                <p className="text-xs text-gray-500">
                    On offer at {offers.length} stores
                    {priceGap > 0 && ` · up to ${formatCurrency(priceGap)}${byUnitPrice ? `/${offers[0].unit_price_unit}` : ''} apart`}
                </p>
            </div>
            <ol className="flex flex-col gap-1">
                {offers.map((offer, index) => (
                    <RetailerPriceRow key={offer.id} offer={offer} cheapest={index === 0} byUnitPrice={byUnitPrice} />
                ))}
            </ol>
        </li>
    );
}
