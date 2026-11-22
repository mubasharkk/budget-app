import { useState } from 'react';
import axios from 'axios';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/utils/money';
import PackSizeFields from './PackSizeFields';

const LIST_ID = 'insights-general-names';
const MISSING = { name: 'no general name', size: 'no pack size', unit: 'measured differently from the rest of its name' };

function FixRow({ row, onFixed }) {
    const [groupName, setGroupName] = useState(row.group_name ?? '');
    const [pack, setPack] = useState({ size: '', unit: '' });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const asksForName = row.missing.includes('name') || row.missing.includes('unit');
    const asksForSize = row.missing.includes('size') || row.missing.includes('unit');
    const renamed = groupName.trim() !== '' && groupName.trim() !== (row.group_name ?? '');
    const hasPack = pack.size !== '' || pack.unit !== '';
    const canSave = row.product_id === null ? groupName.trim() !== '' : renamed || hasPack;

    const save = (e) => {
        e.preventDefault();
        setSaving(true);
        setError(null);

        const changes = {
            ...(renamed ? { group_name: groupName.trim() } : {}),
            ...(hasPack ? { size: pack.size || null, unit: pack.unit || null } : {}),
        };
        const request =
            row.product_id === null
                ? axios.post(route('insights.unmatched-items.store'), { ...changes, name: row.name })
                : axios.patch(route('insights.products.update', row.product_id), changes);

        request
            .then(() => onFixed(row.label))
            .catch((err) => setError(err.response?.data?.message ?? 'That could not be saved. Please try again.'))
            .finally(() => setSaving(false));
    };

    return (
        <li className="py-3">
            <form onSubmit={save} noValidate className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between md:gap-4">
                <div className="min-w-0">
                    <div className="truncate text-sm font-medium text-gray-900">{row.label}</div>
                    <div className="text-xs text-gray-600">
                        {row.missing.map((part) => MISSING[part]).join(', ')} · bought {row.purchases}× ·{' '}
                        {formatCurrency(row.spend)}
                    </div>
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {asksForName && (
                        <TextInput
                            list={LIST_ID}
                            maxLength={100}
                            value={groupName}
                            onChange={(e) => setGroupName(e.target.value)}
                            placeholder="General name"
                            aria-label={`General name of ${row.label}`}
                            className="w-44 py-1.5 text-sm"
                        />
                    )}
                    {asksForSize && <PackSizeFields {...pack} onChange={setPack} name={row.label} />}
                    <SecondaryButton type="submit" disabled={saving || !canSave}>
                        {saving ? 'Saving…' : 'Save'}
                    </SecondaryButton>
                </div>
            </form>
            {error && (
                <p className="mt-1 text-xs text-red-700" role="alert">
                    {error}
                </p>
            )}
        </li>
    );
}

/**
 * How complete the figures are, and the fixes that would improve them most.
 */
export default function DataQualityPanel({ coverage, gaps, onFixed }) {
    const [fixed, setFixed] = useState(null);

    if (!coverage && !gaps?.count) {
        return null;
    }

    const done = (label) => {
        setFixed(label);
        onFixed();
    };

    return (
        <section className="min-w-0 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
            <h3 className="text-base font-medium text-gray-900">Make your figures more exact</h3>
            {coverage && (
                <p className="text-sm text-gray-600">
                    In this period {coverage.named_percent}% of your spend has a general name and {coverage.measured_percent}%
                    has a known pack size.
                </p>
            )}

            <p className="text-sm text-green-700" role="status">
                {fixed && `Saved ${fixed}. It applies to all its purchases.`}
            </p>

            {gaps &&
                (gaps.count === 0 ? (
                    <p className="mt-3 text-sm text-gray-600">Nothing needs fixing in the last 180 days.</p>
                ) : (
                    <>
                        <p className="mt-3 text-xs text-gray-600">
                            {gaps.count} {gaps.count === 1 ? 'thing affects' : 'things affect'} {gaps.affected_percent}% of the
                            last 180 days&apos; spend, biggest first. For a pack, give its size and unit. For things sold by
                            weight choose kg, and for things counted singly choose pieces, both without a size.
                        </p>
                        <datalist id={LIST_ID}>
                            {gaps.group_names.map((name) => (
                                <option key={name} value={name} />
                            ))}
                        </datalist>
                        <ul className="mt-1 divide-y divide-gray-100">
                            {gaps.rows.map((row) => (
                                <FixRow key={`${row.key}:${row.missing.join()}`} row={row} onFixed={done} />
                            ))}
                        </ul>
                        {gaps.count > gaps.rows.length && (
                            <p className="text-xs text-gray-600">
                                {gaps.count - gaps.rows.length} more will appear as you fix these.
                            </p>
                        )}
                    </>
                ))}
        </section>
    );
}
