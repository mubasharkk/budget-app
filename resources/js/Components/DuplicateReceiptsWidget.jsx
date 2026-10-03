import { CheckCircleIcon } from '@heroicons/react/24/outline';
import { usePage } from '@inertiajs/react';
import DuplicateReceiptRow from '@/Components/DuplicateReceiptRow';

export default function DuplicateReceiptsWidget() {
    const { duplicateReceipts = [] } = usePage().props;

    return (
        <div className="p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">
                        Possible duplicate receipts
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Uploads matching an earlier receipt are held back from your totals until you decide.
                    </p>
                </div>
                {duplicateReceipts.length > 0 && (
                    <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                        {duplicateReceipts.length}
                    </span>
                )}
            </div>

            {duplicateReceipts.length === 0 ? (
                <div className="mt-6 flex flex-col items-center gap-2 py-6 text-center text-sm text-gray-500">
                    <CheckCircleIcon className="h-8 w-8 text-green-500" />
                    No duplicate receipts found.
                </div>
            ) : (
                <ul className="mt-4 divide-y divide-gray-100">
                    {duplicateReceipts.map((receipt) => (
                        <DuplicateReceiptRow key={receipt.id} receipt={receipt} tone="gray" />
                    ))}
                </ul>
            )}
        </div>
    );
}
