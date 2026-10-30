import { usePage } from '@inertiajs/react';
import { SparklesIcon } from '@heroicons/react/24/outline';
import ForYouCard from '@/Components/Deals/ForYouCard';
import SummaryTiles from '@/Components/Deals/SummaryTiles';

export default function ForYouSection() {
    const forYou = usePage().props.forYou;

    if (!forYou || forYou.recommendations.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-4">
            <SummaryTiles summary={forYou.summary} />
            <h3 className="flex items-center gap-2 text-base font-medium text-gray-900">
                <SparklesIcon className="h-5 w-5 text-brand-primary" aria-hidden="true" />
                For you
            </h3>
            <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {forYou.recommendations.map((recommendation) => (
                    <ForYouCard key={recommendation.offer.id} recommendation={recommendation} />
                ))}
            </ul>
        </section>
    );
}
