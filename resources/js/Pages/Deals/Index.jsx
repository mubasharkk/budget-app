import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DealsTabs from '@/Components/Deals/DealsTabs';
import MyPricesTab from '@/Components/Deals/MyPricesTab';
import OffersTab from '@/Components/Deals/OffersTab';
import { showOffers, showPrices } from '@/Components/Deals/visitDeals';

const TABS = [
    { value: 'offers', label: 'Offers' },
    { value: 'prices', label: 'My prices' },
];

export default function DealsIndex({ tab, filters, priceRange, retailers, sortOptions, weekOptions, ...data }) {
    const selectTab = (value) => (value === 'prices' ? showPrices(priceRange) : showOffers(filters));

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Deals</h2>}
        >
            <Head title="Deals" />

            <div className="py-12">
                <div className="mx-auto flex max-w-7xl flex-col gap-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <DealsTabs tabs={TABS} active={tab} onSelect={selectTab} />
                    {tab === 'prices' ? (
                        <MyPricesTab range={priceRange} prices={data.prices} />
                    ) : (
                        <OffersTab
                            filters={filters}
                            options={{ retailers, sorts: sortOptions, weeks: weekOptions }}
                            syncedAt={data.syncedAt}
                            availableRetailers={data.availableRetailers}
                            offers={data.offers}
                        />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
