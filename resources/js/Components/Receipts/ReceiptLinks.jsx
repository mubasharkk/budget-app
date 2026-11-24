import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { LinkSlashIcon } from '@heroicons/react/24/outline';
import InputError from '@/Components/InputError';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * What a receipt is attached to (contracts, savings entries), with a choice to
 * attach it to another. Attaching never changes spending totals.
 */
export default function ReceiptLinks({ receiptId, links, attachOptions = [] }) {
    const [choice, setChoice] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const groups = attachOptions.filter((group) => group.options.length > 0);

    const send = (method, type, id) => {
        const data = { receipt_id: receiptId, link_type: type, link_id: id };
        const options = {
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setError(null);
            },
            onSuccess: () => setChoice(''),
            onError: (errors) => setError(Object.values(errors)[0] ?? 'That did not work. Please try again.'),
            onFinish: () => setBusy(false),
        };

        if (method === 'post') {
            router.post(route('receipt-links.store'), data, options);
        } else {
            router.delete(route('receipt-links.destroy'), { ...options, data });
        }
    };

    const attach = (event) => {
        event.preventDefault();
        const [type, id] = choice.split(':');
        send('post', type, Number(id));
    };

    if (links.length === 0 && groups.length === 0) {
        return null;
    }

    return (
        <div className="mt-6">
            <h3 className="text-lg font-medium">Attached to</h3>
            <p className="mt-1 text-sm text-gray-500">Contracts or savings entries this receipt belongs to. Attaching does not change your spending totals.</p>

            {links.length > 0 && (
                <ul className="mt-3 divide-y divide-gray-100 rounded-lg border">
                    {links.map((link) => (
                        <li key={`${link.type}-${link.id}`} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <Link href={link.url} className="min-w-0 text-blue-600 hover:text-blue-800">
                                <span className="mr-2 text-xs font-medium uppercase tracking-wide text-gray-500">{link.type_label}</span>
                                <span className="truncate">{link.label}</span>
                            </Link>
                            <button
                                type="button"
                                onClick={() => send('delete', link.type, link.id)}
                                disabled={busy}
                                className="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                                title="Detach"
                                aria-label={`Detach from ${link.label}`}
                            >
                                <LinkSlashIcon className="h-4 w-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {groups.length > 0 && (
                <form onSubmit={attach} className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <label htmlFor="attach-receipt-to" className="sr-only">
                        Attach to
                    </label>
                    <select
                        id="attach-receipt-to"
                        value={choice}
                        onChange={(event) => setChoice(event.target.value)}
                        className="min-w-0 flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Attach to a contract or savings entry…</option>
                        {groups.map((group) => (
                            <optgroup key={group.type} label={group.label}>
                                {group.options.map((option) => (
                                    <option key={option.id} value={`${group.type}:${option.id}`}>
                                        {option.label}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </select>
                    <SecondaryButton type="submit" disabled={!choice || busy} className="justify-center">
                        Attach
                    </SecondaryButton>
                </form>
            )}
            <InputError message={error} className="mt-2" />
        </div>
    );
}
