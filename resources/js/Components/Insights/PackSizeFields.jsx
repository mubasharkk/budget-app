import TextInput from '@/Components/TextInput';

const UNITS = [
    { value: 'g', label: 'g' },
    { value: 'kg', label: 'kg' },
    { value: 'ml', label: 'ml' },
    { value: 'l', label: 'l' },
    { value: 'pcs', label: 'pieces' },
];

/**
 * The size of one pack. Left empty with kg it is sold by weight, with pieces
 * it is counted one by one.
 */
export default function PackSizeFields({ size, unit, onChange, name }) {
    return (
        <div className="flex gap-2">
            <TextInput
                type="number"
                inputMode="decimal"
                min="0"
                step="any"
                value={size}
                onChange={(e) => onChange({ size: e.target.value, unit })}
                placeholder="Size"
                aria-label={`Pack size of ${name}`}
                className="w-24 py-1.5 text-sm"
            />
            <select
                value={unit}
                onChange={(e) => onChange({ size, unit: e.target.value })}
                aria-label={`Unit of ${name}`}
                className="w-28 rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">Unit…</option>
                {UNITS.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}
