import { usePage } from '@inertiajs/react';
import { ScaleIcon } from '@heroicons/react/24/outline';
import CompareCard from '@/Components/Deals/CompareCard';

export default function CompareSection() {
    const comparisons = usePage().props.comparisons;

    if (!comparisons || comparisons.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-4">
            <h3 className="flex items-center gap-2 text-base font-medium text-gray-900">
                <ScaleIcon className="h-5 w-5 text-brand-primary" aria-hidden="true" />
                Where it's cheapest
            </h3>
            <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {comparisons.map((comparison) => (
                    <CompareCard key={comparison.product.id} comparison={comparison} />
                ))}
            </ul>
        </section>
    );
}
