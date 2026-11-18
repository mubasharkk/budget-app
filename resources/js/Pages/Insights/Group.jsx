import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, PencilSquareIcon } from '@heroicons/react/24/outline';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { formatAmount } from '@/Components/Insights/consumedAmount';
import { formatCurrency } from '@/utils/money';

const UNIT_OPTIONS = [
    { value: 'l', label: 'Litres' },
    { value: 'kg', label: 'Kilograms' },
    { value: 'pcs', label: 'Pieces' },
];

const selectClasses =
    'rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const dateFormat = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
const formatDate = (date) => (date ? dateFormat.format(new Date(`${date}T00:00:00`)) : '—');

function Panel({ title, children, action }) {
    return (
        <section className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <div className="mb-4 flex items-center justify-between gap-4">
                <h3 className="text-base font-medium text-gray-900">{title}</h3>
                {action}
            </div>
            {children}
        </section>
    );
}

function Stat({ label, value, hint }) {
    return (
        <div className="bg-white p-4 shadow-sm sm:rounded-lg">
            <div className="text-xs font-medium uppercase tracking-wider text-gray-500">{label}</div>
            <div className="mt-1 text-xl font-semibold tabular-nums text-gray-900">{value}</div>
            {hint && <div className="mt-0.5 text-xs text-gray-500">{hint}</div>}
        </div>
    );
}

function RenameForm({ group, onDone }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: group.name,
        unit: group.unit ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('insights.groups.update', group.id), { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-start gap-2">
            <div>
                <TextInput
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    aria-label="General name"
                    isFocused
                />
                <InputError message={errors.name} className="mt-1" />
            </div>
            <select
                value={data.unit}
                onChange={(e) => setData('unit', e.target.value)}
                className={selectClasses}
                aria-label="Counted in"
            >
                <option value="">Unit not set</option>
                {UNIT_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <PrimaryButton disabled={processing}>Save</PrimaryButton>
            <SecondaryButton type="button" onClick={onDone}>
                Cancel
            </SecondaryButton>
        </form>
    );
}

function MergeForm({ group, otherGroups }) {
    const { data, setData, post, processing, errors } = useForm({ into: '' });

    if (otherGroups.length === 0) {
        return null;
    }

    const submit = (e) => {
        e.preventDefault();
        const target = otherGroups.find((other) => String(other.id) === String(data.into));
        if (target && window.confirm(`Merge “${group.name}” into “${target.name}”?`)) {
            post(route('insights.groups.merge', group.id));
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
            <label htmlFor="merge-into" className="text-sm text-gray-600">
                Same thing as
            </label>
            <select
                id="merge-into"
                value={data.into}
                onChange={(e) => setData('into', e.target.value)}
                className={selectClasses}
            >
                <option value="">Choose…</option>
                {otherGroups.map((other) => (
                    <option key={other.id} value={other.id}>
                        {other.name}
                    </option>
                ))}
            </select>
            <SecondaryButton type="submit" disabled={processing || !data.into}>
                Merge
            </SecondaryButton>
            <InputError message={errors.into} />
        </form>
    );
}

function MoveProduct({ product, groupName, otherGroups }) {
    const [name, setName] = useState('');
    const listId = `groups-for-${product.id}`;

    const submit = (e) => {
        e.preventDefault();
        router.patch(
            route('products.consumable-group.update', product.id),
            { group_name: name },
            { preserveScroll: true, onSuccess: () => setName('') },
        );
    };

    return (
        <form onSubmit={submit} className="flex items-center gap-2">
            <input
                list={listId}
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Move to…"
                aria-label={`Move ${product.name} to another general name`}
                className="w-32 rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <datalist id={listId}>
                {otherGroups.map((other) => (
                    <option key={other.id} value={other.name} />
                ))}
            </datalist>
            <button
                type="submit"
                disabled={!name.trim() || name.trim().toLowerCase() === groupName.toLowerCase()}
                className="text-sm font-medium text-indigo-600 hover:text-indigo-800 disabled:text-gray-300"
            >
                Move
            </button>
        </form>
    );
}

export default function Group({ group, totals, monthly, vendors, products, purchases, other_groups: otherGroups }) {
    const flash = usePage().props.flash ?? {};
    const [renaming, setRenaming] = useState(false);
    const unitLabel = (amount) => formatAmount({ amount, unit: totals.unit });
    const hasAmounts = monthly.some((month) => month.amount !== null);

    const setCounts = (purchase) =>
        router.patch(
            route('receipt-items.consumption.update', purchase.id),
            { counts: !purchase.counts },
            { preserveScroll: true },
        );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center gap-3">
                    <Link href={route('insights')} className="text-gray-400 hover:text-gray-700" title="Back to Insights">
                        <ArrowLeftIcon className="h-5 w-5" />
                    </Link>
                    {renaming ? (
                        <RenameForm group={group} onDone={() => setRenaming(false)} />
                    ) : (
                        <>
                            <h2 className="text-xl font-semibold leading-tight text-gray-800">{group.name}</h2>
                            <button
                                type="button"
                                onClick={() => setRenaming(true)}
                                className="text-gray-400 hover:text-gray-700"
                                title="Rename"
                            >
                                <PencilSquareIcon className="h-5 w-5" />
                            </button>
                        </>
                    )}
                </div>
            }
        >
            <Head title={group.name} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">{flash.success}</div>
                    )}

                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <Stat
                            label="Bought in total"
                            value={unitLabel(totals.amount) ?? `${totals.purchases}×`}
                            hint={
                                totals.unmeasured_count > 0 && totals.amount !== null
                                    ? `+ ${totals.unmeasured_count} without a known size`
                                    : `${totals.purchases} purchases`
                            }
                        />
                        <Stat label="Spent" value={formatCurrency(totals.spend)} />
                        <Stat
                            label="How often"
                            value={
                                totals.days_between_purchases !== null
                                    ? `every ${totals.days_between_purchases} days`
                                    : '—'
                            }
                            hint={`last bought ${formatDate(totals.last_bought)}`}
                        />
                        <Stat
                            label="Average price"
                            value={
                                totals.price_per_unit !== null
                                    ? `${formatCurrency(totals.price_per_unit)} / ${totals.unit}`
                                    : '—'
                            }
                        />
                    </div>

                    <Panel title="Per month">
                        {monthly.every((month) => month.purchases === 0) ? (
                            <p className="py-8 text-center text-sm text-gray-400">
                                Not bought in the last 12 months.
                            </p>
                        ) : (
                            <ResponsiveContainer width="100%" height={260}>
                                <BarChart data={monthly}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                    <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                                    <YAxis
                                        tick={{ fontSize: 12 }}
                                        width={70}
                                        tickFormatter={(value) =>
                                            hasAmounts ? unitLabel(value) : formatCurrency(value)
                                        }
                                    />
                                    <Tooltip
                                        formatter={(value) =>
                                            hasAmounts ? unitLabel(value) : formatCurrency(value)
                                        }
                                    />
                                    <Bar
                                        dataKey={hasAmounts ? 'amount' : 'spend'}
                                        name={hasAmounts ? 'Amount' : 'Spent'}
                                        fill="#10B981"
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </Panel>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <Panel title="Where it's cheapest">
                            {vendors.length === 0 ? (
                                <p className="text-sm text-gray-400">No purchases yet.</p>
                            ) : (
                                <ul className="divide-y divide-gray-100">
                                    {vendors.map((vendor, index) => (
                                        <li key={vendor.vendor} className="flex items-center justify-between gap-4 py-2.5">
                                            <div className="min-w-0">
                                                <div className="truncate text-sm font-medium text-gray-900">
                                                    {vendor.vendor}
                                                    {index === 0 && vendor.price_per_unit !== null && vendors.length > 1 && (
                                                        <span className="ml-2 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                                            Cheapest
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    bought {vendor.purchases}× · {formatCurrency(vendor.spend)}
                                                </div>
                                            </div>
                                            <div className="shrink-0 text-sm font-semibold tabular-nums text-gray-900">
                                                {vendor.price_per_unit !== null
                                                    ? `${formatCurrency(vendor.price_per_unit)} / ${totals.unit}`
                                                    : '—'}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>

                        <Panel title="Products" action={<MergeForm group={group} otherGroups={otherGroups} />}>
                            <ul className="divide-y divide-gray-100">
                                {products.map((product) => (
                                    <li key={product.id} className="flex items-center justify-between gap-4 py-2.5">
                                        <div className="min-w-0">
                                            <Link
                                                href={route('products.show', product.id)}
                                                className="block truncate text-sm font-medium text-gray-900 hover:text-indigo-700 hover:underline"
                                            >
                                                {product.name}
                                            </Link>
                                            <div className="text-xs text-gray-500">
                                                {[product.brand, product.pack].filter(Boolean).join(' · ') || 'No pack size'}
                                                {' · '}bought {product.purchases}× · {formatCurrency(product.spend)}
                                            </div>
                                        </div>
                                        <MoveProduct product={product} groupName={group.name} otherGroups={otherGroups} />
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    </div>

                    <Panel title="Recent purchases">
                        {purchases.length === 0 ? (
                            <p className="text-sm text-gray-400">No purchases yet.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {purchases.map((purchase) => (
                                    <li key={purchase.id} className="flex items-center justify-between gap-4 py-2.5">
                                        <div className={`min-w-0 ${purchase.counts ? '' : 'opacity-50'}`}>
                                            <div className="truncate text-sm text-gray-900">
                                                <Link
                                                    href={route('receipts.show', purchase.receipt_id)}
                                                    className="font-medium hover:text-indigo-700 hover:underline"
                                                >
                                                    {purchase.name}
                                                </Link>
                                                <span className="text-gray-500"> · {purchase.product_name}</span>
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {formatDate(purchase.date)} · {purchase.vendor ?? 'Unknown shop'} · ×
                                                {purchase.quantity}
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-4">
                                            <span
                                                className={`text-sm font-semibold tabular-nums ${
                                                    purchase.counts ? 'text-gray-900' : 'text-gray-400 line-through'
                                                }`}
                                            >
                                                {formatCurrency(purchase.total)}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setCounts(purchase)}
                                                className="w-24 text-right text-xs font-medium text-indigo-600 hover:text-indigo-800"
                                            >
                                                {purchase.counts ? 'Leave out' : 'Count again'}
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
