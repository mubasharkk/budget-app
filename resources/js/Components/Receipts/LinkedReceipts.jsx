import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { LinkSlashIcon, ReceiptPercentIcon } from '@heroicons/react/24/outline';
import InputError from '@/Components/InputError';
import ReceiptKindIcon from '@/Components/ReceiptKindIcon';
import ReceiptPicker from '@/Components/Receipts/ReceiptPicker';

/**
 * The receipts attached to a contract or savings entry (`link` = { type, id }),
 * with a search to attach another. Attaching never changes spending totals.
 */
export default function LinkedReceipts({ receipts, link, description }) {
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);

    const visit = (method, receipt) => {
        setBusy(true);
        setError(null);
        const data = { receipt_id: receipt.id, link_type: link.type, link_id: link.id };
        const options = {
            preserveScroll: true,
            onError: (errors) => setError(Object.values(errors)[0] ?? 'That did not work. Please try again.'),
            onFinish: () => setBusy(false),
        };

        if (method === 'post') {
            router.post(route('receipt-links.store'), data, options);
        } else {
            router.delete(route('receipt-links.destroy'), { ...options, data });
        }
    };

    return (
        <section className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <div className="flex items-center gap-2">
                <ReceiptPercentIcon className="h-5 w-5 text-indigo-500" />
                <h3 className="text-lg font-medium text-gray-900">Receipts</h3>
            </div>
            {description && <p className="mt-1 text-sm text-gray-500">{description}</p>}

            {receipts.length > 0 && (
                <ul className="mt-4 divide-y divide-gray-100 rounded-lg border">
                    {receipts.map((receipt) => (
                        <li key={receipt.id} className="flex items-center gap-3 px-3 py-2 text-sm">
                            <ReceiptKindIcon kind={receipt.kind} />
                            <Link href={receipt.url} className="min-w-0 flex-1 hover:text-indigo-700">
                                <span className="block truncate font-medium text-gray-900">{receipt.vendor}</span>
                                <span className="block text-xs text-gray-500">{receipt.date_label ?? 'No date'}</span>
                            </Link>
                            <span className="shrink-0 tabular-nums text-gray-900">{receipt.amount_label}</span>
                            <button
                                type="button"
                                onClick={() => visit('delete', receipt)}
                                disabled={busy}
                                className="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                                title="Detach receipt"
                                aria-label={`Detach the ${receipt.vendor} receipt`}
                            >
                                <LinkSlashIcon className="h-4 w-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-4">
                <label htmlFor={`attach-receipt-${link.type}-${link.id}`} className="text-sm font-medium text-gray-700">
                    Attach a receipt
                </label>
                <ReceiptPicker
                    id={`attach-receipt-${link.type}-${link.id}`}
                    selected={null}
                    onChange={(receipt) => receipt && visit('post', receipt)}
                    excludeIds={receipts.map((receipt) => receipt.id)}
                />
                <InputError message={error} className="mt-2" />
            </div>
        </section>
    );
}
