import { useState } from 'react';
import OfferRow from '@/Components/Deals/OfferRow';
import { fetchDealOffers } from '@/Components/Deals/dealsApi';

export default function OfferGroup({ group, filters }) {
    const [allOffers, setAllOffers] = useState(null);
    const [expanding, setExpanding] = useState(false);
    const offers = allOffers ?? group.offers;
    const hidden = group.total - offers.length;

    const showAll = () => {
        setExpanding(true);
        fetchDealOffers({ ...filters, category: group.category_id })
            .then((data) => setAllOffers(data.groups[0]?.offers ?? group.offers))
            .finally(() => setExpanding(false));
    };

    return (
        <section className="flex flex-col gap-2">
            <h4 className="text-sm text-gray-500">
                {group.name} · {group.total} offers
            </h4>
            <ul className="divide-y divide-gray-100 rounded-lg border border-gray-200">
                {offers.map((offer) => (
                    <OfferRow key={offer.id} offer={offer} />
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
