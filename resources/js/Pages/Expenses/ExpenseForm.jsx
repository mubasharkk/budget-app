import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function ExpenseForm({
    data,
    setData,
    errors,
    expenseTypes = [],
    currencies = [],
}) {
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
                <InputLabel htmlFor="spent_on" value="Spent on" />
                <TextInput
                    id="spent_on"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.spent_on}
                    onChange={(e) => setData('spent_on', e.target.value)}
                />
                <InputError message={errors.spent_on} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="expense_type" value="Type" />
                <select
                    id="expense_type"
                    className={selectClasses}
                    value={data.expense_type}
                    onChange={(e) => setData('expense_type', e.target.value)}
                >
                    {expenseTypes.map((t) => (
                        <option key={t.value} value={t.value}>
                            {t.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.expense_type} className="mt-2" />
            </div>

            <div className="sm:col-span-2">
                <InputLabel htmlFor="description" value="Description" />
                <TextInput
                    id="description"
                    type="text"
                    className="mt-1 block w-full"
                    value={data.description ?? ''}
                    onChange={(e) => setData('description', e.target.value)}
                    placeholder="e.g. Client lunch, office supplies, repair"
                />
                <InputError message={errors.description} className="mt-2" />
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
