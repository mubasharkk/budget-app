import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import ReceiptPicker from '@/Components/Receipts/ReceiptPicker';

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

/**
 * Choose the receipt, contract, budget or savings entry a task is about. Receipts are
 * searched (there are too many to list); the others are short lists.
 */
export default function TaskLinkPicker({ data, setData, errors, linkTypes, linkOptions }) {
    const records = linkOptions[data.link_type] ?? [];

    return (
        <div className="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2">
            <div>
                <InputLabel htmlFor="link_type" value="About (optional)" />
                <select
                    id="link_type"
                    className={selectClasses}
                    value={data.link_type}
                    onChange={(e) => setData({ ...data, link_type: e.target.value, link_id: '', link_receipt: null })}
                >
                    <option value="">Nothing in particular</option>
                    {linkTypes.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.link_type} className="mt-2" />
            </div>

            {data.link_type === 'receipt' && (
                <div>
                    <InputLabel htmlFor="link_receipt" value="Which receipt" />
                    <ReceiptPicker
                        id="link_receipt"
                        selected={data.link_receipt}
                        onChange={(receipt) => setData({ ...data, link_id: receipt?.id ?? '', link_receipt: receipt })}
                    />
                    <InputError message={errors.link_id} className="mt-2" />
                </div>
            )}

            {data.link_type && data.link_type !== 'receipt' && (
                <div>
                    <InputLabel htmlFor="link_id" value="Which one" />
                    <select
                        id="link_id"
                        className={selectClasses}
                        value={data.link_id}
                        onChange={(e) => setData('link_id', e.target.value)}
                    >
                        <option value="">Choose…</option>
                        {records.map((record) => (
                            <option key={record.value} value={record.value}>
                                {record.label}
                            </option>
                        ))}
                    </select>
                    {records.length === 0 && (
                        <p className="mt-1 text-xs text-gray-500">Nothing to link yet.</p>
                    )}
                    <InputError message={errors.link_id} className="mt-2" />
                </div>
            )}
        </div>
    );
}
