import { useEffect, useState } from 'react';
import axios from 'axios';
import Checkbox from '@/Components/Checkbox';
import PrimaryButton from '@/Components/PrimaryButton';

/**
 * Lets the user choose which categories count as consumption. Unticking a
 * parent category leaves out its subcategories too. Saved per user.
 */
export default function ConsumptionScopePanel({ categories, excludedIds, onSaved }) {
    const [open, setOpen] = useState(false);
    const [excluded, setExcluded] = useState(excludedIds);
    const [saving, setSaving] = useState(false);
    const parents = categories.filter((category) => category.type === 'parent');

    useEffect(() => setExcluded(excludedIds), [excludedIds]);

    const toggle = (id) =>
        setExcluded((current) =>
            current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
        );

    const save = () => {
        setSaving(true);
        axios
            .put(route('insights.settings.update'), { excluded_category_ids: excluded })
            .then(() => {
                setOpen(false);
                onSaved();
            })
            .finally(() => setSaving(false));
    };

    return (
        <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full items-center justify-between text-left"
                aria-expanded={open}
            >
                <span className="text-base font-medium text-gray-900">
                    What counts as consumption
                </span>
                <span className="text-sm text-gray-500">
                    {excludedIds.length === 0
                        ? 'All categories'
                        : `${excludedIds.length} ${excludedIds.length === 1 ? 'category' : 'categories'} left out`}
                </span>
            </button>

            {open && (
                <div className="mt-4 space-y-4">
                    <p className="text-sm text-gray-500">
                        Deposits, bags, discounts and fees never count. Untick
                        categories you buy but don't consume, such as household
                        goods or electronics.
                    </p>
                    {parents.length === 0 ? (
                        <p className="text-sm text-gray-400">No categories yet.</p>
                    ) : (
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {parents.map((category) => (
                                <label
                                    key={category.id}
                                    className="flex items-center gap-2 text-sm text-gray-700"
                                >
                                    <Checkbox
                                        checked={!excluded.includes(category.id)}
                                        onChange={() => toggle(category.id)}
                                    />
                                    {category.name}
                                </label>
                            ))}
                        </div>
                    )}
                    <PrimaryButton type="button" onClick={save} disabled={saving}>
                        {saving ? 'Saving…' : 'Save'}
                    </PrimaryButton>
                </div>
            )}
        </div>
    );
}
