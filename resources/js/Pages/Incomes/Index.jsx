import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline';
import { formatCurrency } from '@/utils/money';

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString('de-DE', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';

function MonthlyIncomeCard({ monthlyIncome, incomeTypes, currencies }) {
    const { data, setData, patch, processing, errors, recentlySuccessful } =
        useForm({
            monthly_income: monthlyIncome.amount ?? '',
            income_type: monthlyIncome.income_type ?? 'net',
            income_currency: monthlyIncome.income_currency ?? 'EUR',
        });

    const submit = (e) => {
        e.preventDefault();
        patch(route('incomes.monthly.update'), { preserveScroll: true });
    };

    const typeLabel = incomeTypes.find(
        (t) => t.value === monthlyIncome.income_type,
    )?.label;

    return (
        <div className="rounded-lg bg-white p-5 shadow-sm">
            <div className="flex items-baseline justify-between">
                <h3 className="text-sm font-medium text-gray-500">
                    Monthly income (recurring)
                </h3>
                {monthlyIncome.amount ? (
                    <span className="text-sm text-gray-400">
                        {typeLabel}
                    </span>
                ) : null}
            </div>

            <div className="mt-1 text-2xl font-semibold text-gray-900">
                {monthlyIncome.amount
                    ? formatCurrency(
                          monthlyIncome.amount,
                          monthlyIncome.income_currency,
                      )
                    : 'Not set'}
            </div>
            <p className="mt-1 text-xs text-gray-400">
                Your steady salary. Counts toward every month's income overview.
            </p>

            <form
                onSubmit={submit}
                className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3"
            >
                <div>
                    <InputLabel htmlFor="monthly_income" value="Amount" />
                    <TextInput
                        id="monthly_income"
                        type="number"
                        step="0.01"
                        min="0"
                        className="mt-1 block w-full"
                        value={data.monthly_income}
                        onChange={(e) =>
                            setData('monthly_income', e.target.value)
                        }
                        placeholder="0.00"
                    />
                    <InputError
                        message={errors.monthly_income}
                        className="mt-1"
                    />
                </div>

                <div>
                    <InputLabel htmlFor="income_type" value="Type" />
                    <select
                        id="income_type"
                        className={selectClasses}
                        value={data.income_type}
                        onChange={(e) => setData('income_type', e.target.value)}
                    >
                        {incomeTypes.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.income_type} className="mt-1" />
                </div>

                <div>
                    <InputLabel htmlFor="income_currency" value="Currency" />
                    <select
                        id="income_currency"
                        className={selectClasses}
                        value={data.income_currency}
                        onChange={(e) =>
                            setData('income_currency', e.target.value)
                        }
                    >
                        {currencies.map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </select>
                    <InputError
                        message={errors.income_currency}
                        className="mt-1"
                    />
                </div>

                <div className="flex items-center gap-3 sm:col-span-3">
                    <PrimaryButton disabled={processing}>
                        Save monthly income
                    </PrimaryButton>
                    {recentlySuccessful && (
                        <span className="text-sm text-green-600">Saved.</span>
                    )}
                    <p className="text-xs text-gray-400">
                        Leave the amount blank to clear it.
                    </p>
                </div>
            </form>
        </div>
    );
}

export default function Index({
    incomes,
    summary,
    monthlyIncome,
    incomeTypes = [],
    currencies = [],
}) {
    const flash = usePage().props.flash ?? {};

    const handleDelete = (income) => {
        const label = income.source || 'this income';
        if (window.confirm(`Delete ${label}?`)) {
            router.delete(route('incomes.destroy', income.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        One-time Income
                    </h2>
                    <Link href={route('incomes.create')}>
                        <PrimaryButton icon={PlusIcon}>
                            Add Income
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Income" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <MonthlyIncomeCard
                        monthlyIncome={monthlyIncome}
                        incomeTypes={incomeTypes}
                        currencies={currencies}
                    />

                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-400">
                            One-time income
                        </h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">
                                Total recorded
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {formatCurrency(summary.total)}
                            </div>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">
                                Entries
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {summary.count}
                            </div>
                        </div>
                    </div>

                    {incomes.length === 0 ? (
                        <div className="rounded-lg bg-white p-10 text-center shadow-sm">
                            <p className="text-gray-500">
                                No one-time income yet. Record bonuses, freelance
                                payments, refunds, and other non-recurring
                                earnings here. They count toward your income
                                overview for the month or week received.
                            </p>
                            <div className="mt-4 flex justify-center">
                                <Link href={route('incomes.create')}>
                                    <PrimaryButton icon={PlusIcon}>
                                        Add your first income
                                    </PrimaryButton>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <ul className="divide-y divide-gray-100">
                                {incomes.map((income) => (
                                    <li
                                        key={income.id}
                                        className="flex items-center justify-between px-6 py-4"
                                    >
                                        <div className="min-w-0">
                                            <div className="font-medium text-gray-900">
                                                {income.source || 'One-time income'}
                                            </div>
                                            <div className="mt-0.5 text-sm text-gray-500">
                                                {formatDate(income.received_on)}
                                                {income.income_type &&
                                                    ` · ${income.income_type}`}
                                            </div>
                                            {income.notes && (
                                                <div className="mt-1 text-sm text-gray-400">
                                                    {income.notes}
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <div className="text-right font-semibold text-gray-900">
                                                {formatCurrency(
                                                    income.amount,
                                                    income.currency,
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Link
                                                    href={route(
                                                        'incomes.edit',
                                                        income.id,
                                                    )}
                                                    className="text-gray-400 hover:text-gray-700"
                                                    title="Edit"
                                                >
                                                    <PencilSquareIcon className="h-5 w-5" />
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleDelete(income)
                                                    }
                                                    className="text-gray-400 hover:text-red-600"
                                                    title="Delete"
                                                >
                                                    <TrashIcon className="h-5 w-5" />
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
