import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { ChevronUpDownIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/react/20/solid';
import ReceiptKindIcon from '@/Components/ReceiptKindIcon';

const SEARCH_DELAY = 250;

/**
 * Pick one of the user's receipts by typing a shop, an amount ("23,40") or a
 * date ("03.11", "11.2026"). Shows the most recent receipts before typing.
 * `selected` is a row from receipts.search ({ id, label, vendor, date_label, amount_label, kind }).
 */
export default function ReceiptPicker({ id, selected, onChange, excludeIds = [], placeholder = 'Search shop, amount or date…', autoFocus = false }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const latest = useRef(0);

    useEffect(() => {
        const request = ++latest.current;
        setLoading(true);

        const timer = setTimeout(() => {
            axios
                .get(route('receipts.search'), { params: { q: query } })
                .then((res) => {
                    if (request === latest.current) {
                        setResults(res.data.receipts);
                        setFailed(false);
                    }
                })
                .catch(() => request === latest.current && setFailed(true))
                .finally(() => request === latest.current && setLoading(false));
        }, query === '' ? 0 : SEARCH_DELAY);

        return () => clearTimeout(timer);
    }, [query]);

    const options = results.filter((receipt) => !excludeIds.includes(receipt.id));

    return (
        <Combobox value={selected} onChange={onChange} onClose={() => setQuery('')} by="id" immediate>
            <div className="relative mt-1">
                <MagnifyingGlassIcon className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <ComboboxInput
                    id={id}
                    autoFocus={autoFocus}
                    autoComplete="off"
                    className="block w-full rounded-md border-gray-300 py-2 pl-8 pr-14 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    displayValue={(receipt) => receipt?.label ?? ''}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={placeholder}
                />
                <div className="absolute inset-y-0 right-0 flex items-center pr-1">
                    {selected && (
                        <button
                            type="button"
                            onClick={() => onChange(null)}
                            className="rounded p-1 text-gray-400 hover:text-gray-600"
                            aria-label="Clear receipt"
                        >
                            <XMarkIcon className="h-4 w-4" />
                        </button>
                    )}
                    <ComboboxButton className="rounded p-1 text-gray-400 hover:text-gray-600" aria-label="Show receipts">
                        <ChevronUpDownIcon className="h-4 w-4" />
                    </ComboboxButton>
                </div>
            </div>

            <ComboboxOptions
                anchor="bottom start"
                transition
                className="z-50 mt-1 max-h-72 w-[var(--input-width)] overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg [--anchor-gap:4px]"
            >
                {options.map((receipt) => (
                    <ComboboxOption
                        key={receipt.id}
                        value={receipt}
                        className="flex cursor-pointer items-center gap-2 px-3 py-2 data-[focus]:bg-indigo-50 data-[selected]:font-semibold"
                    >
                        <ReceiptKindIcon kind={receipt.kind} />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-gray-900">{receipt.vendor}</span>
                            <span className="block text-xs text-gray-500">{receipt.date_label ?? 'No date'}</span>
                        </span>
                        <span className="shrink-0 tabular-nums text-gray-900">{receipt.amount_label}</span>
                    </ComboboxOption>
                ))}
                {options.length === 0 && (
                    <div className="px-3 py-2 text-gray-500">
                        {failed ? 'Receipts could not be loaded.' : loading ? 'Searching…' : 'No matching receipts.'}
                    </div>
                )}
            </ComboboxOptions>
        </Combobox>
    );
}
