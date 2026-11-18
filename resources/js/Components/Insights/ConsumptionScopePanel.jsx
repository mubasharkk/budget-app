import { useEffect, useState } from 'react';
import axios from 'axios';
import { AdjustmentsHorizontalIcon } from '@heroicons/react/24/outline';
import Checkbox from '@/Components/Checkbox';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * What counts as consumption, saved per user: which categories count and
 * whether business purchases do. Everything on the Insights page, the
 * dashboard widget and the AI analysis follow it. Unticking a parent category
 * leaves out its subcategories too.
 */
export default function ConsumptionScopePanel({ categories, excludedIds, includeBusiness, onSaved }) {
    const [open, setOpen] = useState(false);
    const [excluded, setExcluded] = useState(excludedIds);
    const [business, setBusiness] = useState(includeBusiness);
    const [saving, setSaving] = useState(false);
    const parents = categories.filter((category) => category.type === 'parent');
    const excludedNames = parents.filter((category) => excludedIds.includes(category.id)).map((category) => category.name);

    useEffect(() => setExcluded(excludedIds), [excludedIds]);
    useEffect(() => setBusiness(includeBusiness), [includeBusiness]);

    const toggle = (id) =>
        setExcluded((current) =>
            current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
        );

    const cancel = () => {
        setExcluded(excludedIds);
        setBusiness(includeBusiness);
        setOpen(false);
    };

    const save = () => {
        setSaving(true);
        axios
            .put(route('insights.settings.update'), {
                excluded_category_ids: excluded,
                include_business: business,
            })
            .then(() => {
                setOpen(false);
                onSaved();
            })
            .finally(() => setSaving(false));
    };

    return (
        <section className="overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg sm:px-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex min-w-0 items-start gap-3">
                    <AdjustmentsHorizontalIcon className="mt-0.5 h-5 w-5 shrink-0 text-indigo-500" />
                    <div className="min-w-0">
                        <h3 className="text-sm font-medium text-gray-900">What counts as consumption</h3>
                        <p className="text-sm text-gray-500">
                            {includeBusiness ? 'Personal and business purchases' : 'Personal purchases only'}
                            {' · '}
                            {excludedNames.length === 0
                                ? 'all categories'
                                : `without ${excludedNames.join(', ')}`}
                            {' · '}deposits, bags, discounts and fees never count
                        </p>
                    </div>
                </div>
                {!open && (
                    <SecondaryButton type="button" onClick={() => setOpen(true)}>
                        Change
                    </SecondaryButton>
                )}
            </div>

            {open && (
                <div className="mt-4 space-y-4 border-t border-gray-100 pt-4">
                    <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <Checkbox checked={business} onChange={(e) => setBusiness(e.target.checked)} />
                        Include business purchases
                    </label>

                    <div>
                        <p className="mb-2 text-sm text-gray-500">
                            Untick categories you buy but don't consume, such as household goods or electronics.
                        </p>
                        {parents.length === 0 ? (
                            <p className="text-sm text-gray-400">No categories yet.</p>
                        ) : (
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                {parents.map((category) => (
                                    <label key={category.id} className="flex items-center gap-2 text-sm text-gray-700">
                                        <Checkbox
                                            checked={!excluded.includes(category.id)}
                                            onChange={() => toggle(category.id)}
                                        />
                                        {category.name}
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="flex gap-2">
                        <PrimaryButton type="button" onClick={save} disabled={saving}>
                            {saving ? 'Saving…' : 'Save'}
                        </PrimaryButton>
                        <SecondaryButton type="button" onClick={cancel}>
                            Cancel
                        </SecondaryButton>
                    </div>
                </div>
            )}
        </section>
    );
}
