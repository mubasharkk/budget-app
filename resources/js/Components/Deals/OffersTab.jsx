import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import BrowseSection from '@/Components/Deals/BrowseSection';
import DealsHeader from '@/Components/Deals/DealsHeader';
import ForYouSection from '@/Components/Deals/ForYouSection';
import PostalCodePrompt from '@/Components/Deals/PostalCodePrompt';

export default function OffersTab({ options }) {
    const postalCode = usePage().props.auth.user.postal_code;
    const [editingPostalCode, setEditingPostalCode] = useState(false);

    if (!postalCode || editingPostalCode) {
        return (
            <PostalCodePrompt
                onSaved={() => setEditingPostalCode(false)}
                onCancel={postalCode ? () => setEditingPostalCode(false) : null}
            />
        );
    }

    return (
        <div key={postalCode} className="flex flex-col gap-6">
            <DealsHeader postalCode={postalCode} onChangePostalCode={() => setEditingPostalCode(true)} />
            <ForYouSection />
            <BrowseSection options={options} />
        </div>
    );
}
