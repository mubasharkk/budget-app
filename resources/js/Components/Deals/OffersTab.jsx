import { useState } from 'react';
import { Deferred, usePage } from '@inertiajs/react';
import BrowseSection from '@/Components/Deals/BrowseSection';
import CompareSection from '@/Components/Deals/CompareSection';
import DealsHeader from '@/Components/Deals/DealsHeader';
import ForYouSection from '@/Components/Deals/ForYouSection';
import PostalCodePrompt from '@/Components/Deals/PostalCodePrompt';
import SkeletonRows from '@/Components/Deals/SkeletonRows';
import useSyncPolling from '@/Components/Deals/useSyncPolling';

export default function OffersTab({ filters, options, syncedAt, availableRetailers, offers }) {
    const postalCode = usePage().props.auth.user.postal_code;
    const [editingPostalCode, setEditingPostalCode] = useState(false);

    useSyncPolling(Boolean(postalCode) && !syncedAt);

    if (!postalCode || editingPostalCode) {
        return (
            <PostalCodePrompt
                onSaved={() => setEditingPostalCode(false)}
                onCancel={postalCode ? () => setEditingPostalCode(false) : null}
            />
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <DealsHeader postalCode={postalCode} onChangePostalCode={() => setEditingPostalCode(true)} />
            <Deferred data={['forYou', 'comparisons']} fallback={<SkeletonRows count={2} height="h-24" />}>
                <ForYouSection />
                <CompareSection />
            </Deferred>
            <BrowseSection
                filters={filters}
                options={{ ...options, availableRetailers: syncedAt ? availableRetailers : null }}
                syncedAt={syncedAt}
                offers={offers}
            />
        </div>
    );
}
