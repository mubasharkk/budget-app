import { Link, router } from '@inertiajs/react';
import { LinkSlashIcon } from '@heroicons/react/24/outline';

/**
 * What a receipt is attached to (contracts, savings entries). Attaching is done
 * from those pages; it can be undone from either side.
 */
export default function ReceiptLinks({ receiptId, links }) {
    if (links.length === 0) {
        return null;
    }

    const detach = (link) =>
        router.delete(route('receipt-links.destroy'), {
            preserveScroll: true,
            data: { receipt_id: receiptId, link_type: link.type, link_id: link.id },
        });

    return (
        <div className="mt-6">
            <h3 className="text-lg font-medium">Attached to</h3>
            <ul className="mt-3 divide-y divide-gray-100 rounded-lg border">
                {links.map((link) => (
                    <li key={`${link.type}-${link.id}`} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                        <Link href={link.url} className="min-w-0 text-blue-600 hover:text-blue-800">
                            <span className="mr-2 text-xs font-medium uppercase tracking-wide text-gray-500">{link.type_label}</span>
                            <span className="truncate">{link.label}</span>
                        </Link>
                        <button
                            type="button"
                            onClick={() => detach(link)}
                            className="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                            title="Detach"
                            aria-label={`Detach from ${link.label}`}
                        >
                            <LinkSlashIcon className="h-4 w-4" />
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
