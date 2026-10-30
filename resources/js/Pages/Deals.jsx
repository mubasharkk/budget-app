import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DealsTabs from '@/Components/Deals/DealsTabs';
import MyPricesTab from '@/Components/Deals/MyPricesTab';
import OffersTab from '@/Components/Deals/OffersTab';

const TABS = [
    { value: 'offers', label: 'Offers' },
    { value: 'prices', label: 'My prices' },
];

const initialTab = () =>
    new URLSearchParams(window.location.search).get('tab') === 'prices' ? 'prices' : 'offers';

export default function Deals({ retailers = [], sortOptions = [], weekOptions = [] }) {
    const [tab, setTab] = useState(initialTab);

    const selectTab = (value) => {
        setTab(value);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', value);
        window.history.replaceState(window.history.state, '', url);
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Deals</h2>}
        >
            <Head title="Deals" />

            <div className="py-12">
                <div className="mx-auto flex max-w-7xl flex-col gap-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <DealsTabs tabs={TABS} active={tab} onSelect={selectTab} />
                    {tab === 'offers' ? (
                        <OffersTab options={{ retailers, sorts: sortOptions, weeks: weekOptions }} />
                    ) : (
                        <MyPricesTab />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
