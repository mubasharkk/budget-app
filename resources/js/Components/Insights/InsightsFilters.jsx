import { useEffect, useState } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { formatRange } from './insightFormat';

const CUSTOM = 'custom';
const selectClasses = 'rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const today = () => new Date().toLocaleDateString('en-CA');

/**
 * The one period and category that every figure on Insights follows.
 */
export default function InsightsFilters({ filters, periods, categories, appliedWindow, onChange }) {
    const [choosingDates, setChoosingDates] = useState(Boolean(filters.start_date));
    const [start, setStart] = useState(filters.start_date ?? '');
    const [end, setEnd] = useState(filters.end_date ?? today());

    useEffect(() => {
        setChoosingDates(Boolean(filters.start_date));
        if (filters.start_date) {
            setStart(filters.start_date);
            setEnd(filters.end_date);
        }
    }, [filters.period, filters.start_date, filters.end_date]);

    const category = filters.category_id ? { category_id: filters.category_id } : {};

    const choosePeriod = (value) => {
        setChoosingDates(value === CUSTOM);
        if (value !== CUSTOM) {
            onChange({ ...category, period: value });
        }
    };

    const chooseCategory = (id) => {
        const { category_id: previous, ...period } = filters;
        onChange(id ? { ...period, category_id: id } : period);
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 px-4 sm:px-0">
            <div className="flex min-w-0 flex-wrap items-center gap-2">
                <select
                    value={choosingDates ? CUSTOM : (filters.period ?? '')}
                    onChange={(e) => choosePeriod(e.target.value)}
                    className={`${selectClasses} font-semibold text-gray-900`}
                    aria-label="Period"
                >
                    {periods.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                    <option value={CUSTOM}>Custom dates…</option>
                </select>

                {choosingDates && (
                    <form
                        className="flex flex-wrap items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            onChange({ ...category, start_date: start, end_date: end });
                        }}
                    >
                        <TextInput
                            type="date"
                            required
                            value={start}
                            max={end || today()}
                            onChange={(e) => setStart(e.target.value)}
                            aria-label="From"
                            className="text-sm"
                        />
                        <TextInput
                            type="date"
                            required
                            value={end}
                            min={start || undefined}
                            max={today()}
                            onChange={(e) => setEnd(e.target.value)}
                            aria-label="To"
                            className="text-sm"
                        />
                        <PrimaryButton disabled={!start || !end}>Apply</PrimaryButton>
                    </form>
                )}

                <select
                    value={filters.category_id ?? ''}
                    onChange={(e) => chooseCategory(e.target.value)}
                    className={`${selectClasses} max-w-full`}
                    aria-label="Category"
                >
                    <option value="">All categories</option>
                    {categories.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </select>
            </div>

            <p className="text-sm text-gray-600" aria-live="polite">
                <span className="font-medium text-gray-900">{formatRange(appliedWindow.start, appliedWindow.end)}</span>
                {' compared with '}
                {formatRange(appliedWindow.previous_start, appliedWindow.previous_end)}
            </p>
        </div>
    );
}
