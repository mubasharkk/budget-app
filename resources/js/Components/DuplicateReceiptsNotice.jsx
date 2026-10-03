import { ExclamationTriangleIcon } from '@heroicons/react/20/solid';
import { usePage } from '@inertiajs/react';
import DuplicateReceiptRow from '@/Components/DuplicateReceiptRow';

export default function DuplicateReceiptsNotice() {
    const { duplicateReceipts = [], sections = [] } = usePage().props;

    const widgetShowsThem = route().current('dashboard') && sections.includes('duplicate_receipts');

    if (duplicateReceipts.length === 0 || widgetShowsThem) {
        return null;
    }

    return (
        <div className="mx-auto mt-4 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="rounded-md border border-amber-200 bg-amber-50 p-4">
                <div className="flex gap-3">
                    <ExclamationTriangleIcon className="h-5 w-5 flex-shrink-0 text-amber-500" />
                    <div className="flex-1">
                        <h3 className="text-sm font-medium text-amber-800">
                            {duplicateReceipts.length === 1
                                ? 'This upload looks like a receipt you already have'
                                : 'These uploads look like receipts you already have'}
                        </h3>
                        <p className="mt-1 text-sm text-amber-700">
                            Same receipt number, date, amount and vendor. It isn't counted until you decide.
                        </p>
                        <ul className="mt-3 divide-y divide-amber-200">
                            {duplicateReceipts.map((receipt) => (
                                <DuplicateReceiptRow key={receipt.id} receipt={receipt} />
                            ))}
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    );
}
