import Chip from '@/Components/Deals/Chip';

export default function RetailerChips({ retailers, selected, onSelect }) {
    return (
        <div className="flex flex-wrap gap-2">
            <Chip active={selected === ''} onClick={() => onSelect('')}>
                All stores
            </Chip>
            {retailers.map((retailer) => (
                <Chip
                    key={retailer.value}
                    active={selected === retailer.value}
                    onClick={() => onSelect(retailer.value)}
                >
                    {retailer.label}
                </Chip>
            ))}
        </div>
    );
}
