import { useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import PackSizeFields from './PackSizeFields';

/**
 * A product's pack on its general name's page, where a wrong size is put right.
 */
export default function EditPackSize({ product }) {
    const initial = { size: product.pack_size ?? '', unit: product.pack_unit ?? '' };
    const [editing, setEditing] = useState(false);
    const [pack, setPack] = useState(initial);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const open = () => {
        setPack(initial);
        setError(null);
        setEditing(true);
    };

    const save = (e) => {
        e.preventDefault();
        setSaving(true);
        axios
            .patch(route('insights.products.update', product.id), { size: pack.size === '' ? null : pack.size, unit: pack.unit })
            .then(() => {
                setEditing(false);
                router.reload({ preserveScroll: true });
            })
            .catch((err) => setError(err.response?.data?.message ?? 'That could not be saved. Please try again.'))
            .finally(() => setSaving(false));
    };

    if (!editing) {
        return (
            <button type="button" onClick={open} className="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                {product.pack_unit ? 'Change pack size' : 'Set pack size'}
            </button>
        );
    }

    return (
        <form onSubmit={save} noValidate className="mt-1 flex flex-wrap items-center gap-2">
            <PackSizeFields {...pack} onChange={setPack} name={product.name} />
            <button type="submit" disabled={saving || !pack.unit} className="text-xs font-medium text-indigo-600 disabled:text-gray-400">
                Save
            </button>
            <button type="button" onClick={() => setEditing(false)} className="text-xs text-gray-600">
                Cancel
            </button>
            {error && (
                <p className="w-full text-xs text-red-700" role="alert">
                    {error}
                </p>
            )}
        </form>
    );
}
