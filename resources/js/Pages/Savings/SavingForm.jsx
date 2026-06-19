import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function SavingForm({ data, setData, errors, currencies = [] }) {
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <InputLabel htmlFor="amount" value="Amount" />
                <TextInput
                    id="amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    className="mt-1 block w-full"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    isFocused
                />
                <InputError message={errors.amount} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="currency" value="Currency" />
                <select
                    id="currency"
                    className={selectClasses}
                    value={data.currency}
                    onChange={(e) => setData('currency', e.target.value)}
                >
                    {currencies.map((c) => (
                        <option key={c} value={c}>
                            {c}
                        </option>
                    ))}
                </select>
                <InputError message={errors.currency} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="saved_on" value="Saved on" />
                <TextInput
                    id="saved_on"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.saved_on}
                    onChange={(e) => setData('saved_on', e.target.value)}
                />
                <InputError message={errors.saved_on} className="mt-2" />
            </div>

            <div className="sm:col-span-2">
                <InputLabel htmlFor="source" value="Source" />
                <TextInput
                    id="source"
                    type="text"
                    className="mt-1 block w-full"
                    value={data.source ?? ''}
                    onChange={(e) => setData('source', e.target.value)}
                    placeholder="e.g. Emergency fund, vacation, investment"
                />
                <InputError message={errors.source} className="mt-2" />
            </div>

            <div className="sm:col-span-2">
                <InputLabel htmlFor="notes" value="Notes" />
                <textarea
                    id="notes"
                    rows={3}
                    className={selectClasses}
                    value={data.notes ?? ''}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <InputError message={errors.notes} className="mt-2" />
            </div>
        </div>
    );
}
