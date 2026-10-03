import { Link, router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/money';

const describe = (receipt) => receipt.vendor || receipt.original_filename;

/**
 * One receipt held back as a duplicate, with links to it and its original and the
 * Discard / Upload anyway choices.
 */
export default function DuplicateReceiptRow({ receipt, tone = 'amber' }) {
    const discard = () =>
        router.delete(route('receipts.destroy', receipt.id), { preserveScroll: true });

    const keep = () =>
        router.patch(route('receipts.keep-duplicate', receipt.id), {}, { preserveScroll: true });

    const text = tone === 'amber' ? 'text-amber-900' : 'text-gray-700';

    return (
        <li className="flex flex-col gap-2 py-2 text-sm sm:flex-row sm:items-center sm:justify-between">
            <span className={text}>
                <Link href={route('receipts.show', receipt.id)} className="font-medium underline">
                    {describe(receipt)}
                </Link>
                {receipt.total_amount && ` · ${formatCurrency(receipt.total_amount)}`}
                {' matches '}
                <Link href={route('receipts.show', receipt.duplicate_of_id)} className="font-medium underline">
                    receipt #{receipt.duplicate_of_id}
                    {receipt.duplicate_of && ` (${describe(receipt.duplicate_of)})`}
                </Link>
            </span>
            <span className="flex flex-shrink-0 gap-2">
                <button
                    type="button"
                    onClick={discard}
                    className="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500"
                >
                    Discard
                </button>
                <button
                    type="button"
                    onClick={keep}
                    className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Upload anyway
                </button>
            </span>
        </li>
    );
}
