import Chip from '@/Components/Deals/Chip';

/**
 * Store filter. Stores without offers near the user (the data source doesn't cover
 * every chain everywhere) are shown disabled with a note, rather than as an empty filter.
 * `available` is null while that is still unknown.
 */
export default function RetailerChips({ retailers, available, selected, onSelect }) {
    const isAvailable = (retailer) => !available || available.includes(retailer.value);
    const missing = retailers.filter((retailer) => !isAvailable(retailer));

    return (
        <div className="flex flex-col gap-1">
            <div className="flex flex-wrap gap-2">
                <Chip active={selected === ''} onClick={() => onSelect('')}>
                    All stores
                </Chip>
                {retailers.map((retailer) => (
                    <Chip
                        key={retailer.value}
                        active={selected === retailer.value}
                        disabled={!isAvailable(retailer) && selected !== retailer.value}
                        title={isAvailable(retailer) ? undefined : 'No offers from this store in your area'}
                        onClick={() => onSelect(retailer.value)}
                    >
                        {retailer.label}
                    </Chip>
                ))}
            </div>
            {missing.length > 0 && (
                <p className="text-xs text-gray-500">
                    No offers in your area from {missing.map((retailer) => retailer.label).join(', ')}: our data
                    source doesn't cover {missing.length === 1 ? 'this store' : 'these stores'} here.
                </p>
            )}
        </div>
    );
}
