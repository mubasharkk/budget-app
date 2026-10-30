import { useState } from 'react';
import OfferRow from '@/Components/Deals/OfferRow';
import { showOffers } from '@/Components/Deals/visitDeals';

export default function OfferGroup({ group, filters }) {
    const [expanding, setExpanding] = useState(false);
    const hidden = group.total - group.offers.length;

    const showAll = () =>
        showOffers(
            { ...filters, expand: group.category_id },
            { onStart: () => setExpanding(true), onFinish: () => setExpanding(false) },
        );

    return (
        <section className="flex flex-col gap-2">
            <h4 className="text-sm text-gray-500">
                {group.name} · {group.total} offers
            </h4>
            <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200">
                {group.offers.map((offer) => (
                    <OfferRow key={offer.id} offer={offer} unitFirst={filters.sort === 'unit_price'} />
                ))}
            </ul>
            {hidden > 0 && (
                <button
                    type="button"
                    onClick={showAll}
                    disabled={expanding}
                    className="self-start text-sm text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                >
                    {expanding ? 'Loading…' : `Show all ${group.total}`}
                </button>
            )}
        </section>
    );
}
