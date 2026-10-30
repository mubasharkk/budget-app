import ForYouCard from '@/Components/Deals/ForYouCard';
import SkeletonRows from '@/Components/Deals/SkeletonRows';
import SummaryTiles from '@/Components/Deals/SummaryTiles';
import { useDealsForYou } from '@/Components/Deals/dealsApi';
import { SparklesIcon } from '@heroicons/react/24/outline';

const LIMIT = 6;

export default function ForYouSection() {
    const { data, loading } = useDealsForYou(LIMIT);

    if (loading) {
        return <SkeletonRows count={2} height="h-24" />;
    }

    if (!data || data.recommendations.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-4">
            <SummaryTiles summary={data.summary} />
            <h3 className="flex items-center gap-2 text-base font-medium text-gray-900">
                <SparklesIcon className="h-5 w-5 text-brand-primary" aria-hidden="true" />
                For you
            </h3>
            <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {data.recommendations.map((recommendation) => (
                    <ForYouCard key={recommendation.offer.id} recommendation={recommendation} />
                ))}
            </ul>
        </section>
    );
}
