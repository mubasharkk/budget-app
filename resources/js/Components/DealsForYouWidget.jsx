import { Link, usePage } from '@inertiajs/react';
import ForYouListItem from '@/Components/Deals/ForYouListItem';
import Notice from '@/Components/Deals/Notice';
import SkeletonRows from '@/Components/Deals/SkeletonRows';
import { useDealsForYou } from '@/Components/Deals/dealsApi';
import { formatCurrency } from '@/utils/money';

const LIMIT = 3;

function WidgetBody({ postalCode }) {
    const { data, loading, error } = useDealsForYou(LIMIT, Boolean(postalCode));

    if (!postalCode) {
        return <Notice tone="info">Add your postal code on the Deals page to see offers near you.</Notice>;
    }

    if (loading) {
        return <SkeletonRows count={LIMIT} />;
    }

    if (error) {
        return <Notice tone="error">{error}</Notice>;
    }

    if (!data.synced_at) {
        return <Notice tone="info">This week's deals for your area are being fetched. Check back in a minute.</Notice>;
    }

    if (data.recommendations.length === 0) {
        return <Notice>No current offers on things you buy.</Notice>;
    }

    return (
        <>
            <p className="text-sm text-gray-500">
                {data.summary.match_count} deals · save up to{' '}
                {formatCurrency(data.summary.possible_saving)}
            </p>
            <ul className="divide-y divide-gray-100">
                {data.recommendations.map((recommendation) => (
                    <ForYouListItem key={recommendation.offer.id} recommendation={recommendation} />
                ))}
            </ul>
        </>
    );
}

export default function DealsForYouWidget() {
    const postalCode = usePage().props.auth.user.postal_code;

    return (
        <div className="flex flex-col gap-3 p-6">
            <div className="flex items-start justify-between gap-3">
                <h3 className="text-lg font-medium text-gray-900">Deals on things you buy</h3>
                <Link href={route('deals')} className="text-sm text-indigo-600 hover:text-indigo-800">
                    All deals
                </Link>
            </div>
            <WidgetBody postalCode={postalCode} />
        </div>
    );
}
