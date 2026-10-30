import { useEffect, useState } from 'react';
import Chip from '@/Components/Deals/Chip';
import RetailerChips from '@/Components/Deals/RetailerChips';
import TextInput from '@/Components/TextInput';

export default function OfferFilters({ filters, options, onChange }) {
    const [search, setSearch] = useState(filters.q);

    useEffect(() => setSearch(filters.q), [filters.q]);

    const submitSearch = (event) => {
        event.preventDefault();
        onChange({ q: search.trim() });
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                {options.weeks.map((week) => (
                    <Chip
                        key={week.value}
                        active={filters.week === week.value}
                        onClick={() => onChange({ week: week.value })}
                    >
                        {week.label}
                    </Chip>
                ))}
            </div>
            <form onSubmit={submitSearch} className="flex flex-wrap gap-2">
                <TextInput
                    type="search"
                    className="block min-w-0 flex-1"
                    placeholder="Käse"
                    aria-label="Search offers"
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        if (event.target.value === '') onChange({ q: '' });
                    }}
                />
                <select
                    aria-label="Sort offers"
                    className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={filters.sort}
                    onChange={(event) => onChange({ sort: event.target.value })}
                >
                    {options.sorts.map((sort) => (
                        <option key={sort.value} value={sort.value}>
                            {sort.label}
                        </option>
                    ))}
                </select>
            </form>
            <div className="flex flex-wrap items-center gap-2">
                <RetailerChips
                    retailers={options.retailers}
                    selected={filters.retailer}
                    onSelect={(retailer) => onChange({ retailer })}
                />
                <Chip active={filters.mine} onClick={() => onChange({ mine: !filters.mine })}>
                    Only my categories
                </Chip>
            </div>
        </div>
    );
}
