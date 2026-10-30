import { useState } from 'react';
import Notice from '@/Components/Deals/Notice';
import OfferFilters from '@/Components/Deals/OfferFilters';
import OfferGroup from '@/Components/Deals/OfferGroup';
import SkeletonRows from '@/Components/Deals/SkeletonRows';
import { useDealOffers } from '@/Components/Deals/dealsApi';
import { formatSyncTime } from '@/utils/deals';

const INITIAL_FILTERS = { q: '', retailer: '', sort: 'saving', week: 'current', mine: false };

const toParams = (filters) =>
    Object.fromEntries(
        Object.entries({ ...filters, mine: filters.mine ? 1 : '' }).filter(([, value]) => value !== ''),
    );

function OfferGroups({ data, loading, error, params }) {
    if (loading) {
        return <SkeletonRows count={6} />;
    }

    if (error) {
        return <Notice tone="error">{error}</Notice>;
    }

    if (!data.synced_at) {
        return (
            <Notice tone="info">
                Fetching this week's deals for {data.postal_code}. This usually takes a minute.
            </Notice>
        );
    }

    if (data.groups.length === 0) {
        return <Notice>No offers match these filters.</Notice>;
    }

    return (
        <>
            <p className="text-xs text-gray-500">
                Updated {formatSyncTime(data.synced_at)} · source: marktguru
            </p>
            {data.groups.map((group) => (
                <OfferGroup
                    key={`${group.category_id}-${JSON.stringify(params)}`}
                    group={group}
                    filters={params}
                />
            ))}
        </>
    );
}

export default function BrowseSection({ options }) {
    const [filters, setFilters] = useState(INITIAL_FILTERS);
    const params = toParams(filters);
    const { data, loading, error } = useDealOffers(params);

    return (
        <section className="flex flex-col gap-4 border-t border-gray-100 pt-6">
            <OfferFilters
                filters={filters}
                options={options}
                onChange={(change) => setFilters((current) => ({ ...current, ...change }))}
            />
            <OfferGroups data={data} loading={loading} error={error} params={params} />
        </section>
    );
}
