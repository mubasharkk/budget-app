import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function BudgetForm({
    data,
    setData,
    errors,
    categories = [],
    periods = [],
    currencies = [],
}) {
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <InputLabel htmlFor="category_id" value="Category" />
                <select
                    id="category_id"
                    className={selectClasses}
                    value={data.category_id ?? ''}
                    onChange={(e) =>
                        setData('category_id', e.target.value || null)
                    }
                >
                    <option value="">All categories (overall)</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </select>
                <InputError message={errors.category_id} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="period" value="Period" />
                <select
                    id="period"
                    className={selectClasses}
                    value={data.period}
                    onChange={(e) => setData('period', e.target.value)}
                >
                    {periods.map((p) => (
                        <option key={p.value} value={p.value}>
                            {p.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.period} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="amount" value="Budget amount" />
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

            <div className="sm:col-span-2">
                <InputLabel htmlFor="starts_on" value="Starts on" />
                <TextInput
                    id="starts_on"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.starts_on}
                    onChange={(e) => setData('starts_on', e.target.value)}
                />
                <InputError message={errors.starts_on} className="mt-2" />
            </div>
        </div>
    );
}
