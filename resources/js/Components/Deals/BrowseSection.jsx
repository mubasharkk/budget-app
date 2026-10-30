import Notice from '@/Components/Deals/Notice';
import OfferFilters from '@/Components/Deals/OfferFilters';
import OfferGroup from '@/Components/Deals/OfferGroup';
import { showOffers } from '@/Components/Deals/visitDeals';
import { formatSyncTime } from '@/utils/deals';

function OfferGroups({ filters, syncedAt, offers }) {
    if (!syncedAt) {
        return <Notice tone="info">Fetching this week's deals for your area. This usually takes a minute.</Notice>;
    }

    if (!offers || offers.length === 0) {
        return <Notice>No offers match these filters.</Notice>;
    }

    return (
        <>
            <p className="text-xs text-gray-500">Updated {formatSyncTime(syncedAt)} · source: marktguru</p>
            {offers.map((group) => (
                <OfferGroup key={group.category_id} group={group} filters={filters} />
            ))}
        </>
    );
}

export default function BrowseSection({ filters, options, syncedAt, offers }) {
    const changeFilters = (change) => showOffers({ ...filters, ...change, expand: null });

    return (
        <section className="flex flex-col gap-4 border-t border-gray-100 pt-6">
            <OfferFilters filters={filters} options={options} onChange={changeFilters} />
            <OfferGroups filters={filters} syncedAt={syncedAt} offers={offers} />
        </section>
    );
}
