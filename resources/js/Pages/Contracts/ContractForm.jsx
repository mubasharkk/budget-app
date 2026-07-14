import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function ContractForm({
    data,
    setData,
    errors,
    providers = [],
    categories = [],
    billingCycles = [],
    statuses = [],
    expenseTypes = [],
    currencies = [],
}) {
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div className="sm:col-span-2">
                <InputLabel htmlFor="name" value="Name" />
                <TextInput
                    id="name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    isFocused
                    placeholder="e.g. Apartment rent, Mobile plan"
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="provider_id" value="Provider" />
                <select
                    id="provider_id"
                    className={selectClasses}
                    value={data.provider_id ?? ''}
                    onChange={(e) =>
                        setData('provider_id', e.target.value || null)
                    }
                >
                    <option value="">— None —</option>
                    {providers.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
                        </option>
                    ))}
                </select>
                <InputError message={errors.provider_id} className="mt-2" />
            </div>

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
                    <option value="">— None —</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </select>
                <InputError message={errors.category_id} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="amount" value="Amount" />
                <TextInput
                    id="amount"
                    type="number"
                    step="0.01"
                    min="0"
                    className="mt-1 block w-full"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
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

            <div>
                <InputLabel htmlFor="billing_cycle" value="Billing cycle" />
                <select
                    id="billing_cycle"
                    className={selectClasses}
                    value={data.billing_cycle}
                    onChange={(e) => setData('billing_cycle', e.target.value)}
                >
                    {billingCycles.map((c) => (
                        <option key={c.value} value={c.value}>
                            {c.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.billing_cycle} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="billing_day"
                    value="Billing day (1–31, optional)"
                />
                <TextInput
                    id="billing_day"
                    type="number"
                    min="1"
                    max="31"
                    className="mt-1 block w-full"
                    value={data.billing_day ?? ''}
                    onChange={(e) =>
                        setData('billing_day', e.target.value || null)
                    }
                />
                <InputError message={errors.billing_day} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="status" value="Status" />
                <select
                    id="status"
                    className={selectClasses}
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                >
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.status} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="start_date" value="Start date" />
                <TextInput
                    id="start_date"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.start_date ?? ''}
                    onChange={(e) => setData('start_date', e.target.value)}
                />
                <InputError message={errors.start_date} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="end_date" value="End date (optional)" />
                <TextInput
                    id="end_date"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.end_date ?? ''}
                    onChange={(e) => setData('end_date', e.target.value || null)}
                />
                <InputError message={errors.end_date} className="mt-2" />
            </div>

            <div>
                <InputLabel
                    htmlFor="next_billing_date"
                    value="Next billing date (optional)"
                />
                <TextInput
                    id="next_billing_date"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.next_billing_date ?? ''}
                    onChange={(e) =>
                        setData('next_billing_date', e.target.value || null)
                    }
                />
                <InputError
                    message={errors.next_billing_date}
                    className="mt-2"
                />
            </div>

            <div className="flex items-center sm:col-span-2">
                <input
                    id="auto_renew"
                    type="checkbox"
                    className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    checked={!!data.auto_renew}
                    onChange={(e) => setData('auto_renew', e.target.checked)}
                />
                <InputLabel
                    htmlFor="auto_renew"
                    value="Auto-renew"
                    className="ms-2"
                />
            </div>

            <div className="sm:col-span-2">
                <InputLabel htmlFor="description" value="Description" />
                <textarea
                    id="description"
                    rows={2}
                    className={selectClasses}
                    value={data.description ?? ''}
                    onChange={(e) => setData('description', e.target.value)}
                />
                <InputError message={errors.description} className="mt-2" />
            </div>

            <div className="sm:col-span-2">
                <InputLabel htmlFor="notes" value="Notes" />
                <textarea
                    id="notes"
                    rows={2}
                    className={selectClasses}
                    value={data.notes ?? ''}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <InputError message={errors.notes} className="mt-2" />
            </div>
        </div>
    );
}
