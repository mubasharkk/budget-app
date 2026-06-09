import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckIcon } from '@heroicons/react/24/outline';

export default function UpdateIncomeForm({ incomeTypeOptions = [], className = '' }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            monthly_income: user.monthly_income ?? '',
            income_type: user.income_type ?? 'net',
            income_currency: user.income_currency ?? 'EUR',
        });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.income.update'));
    };

    const clearIncome = () => {
        router.patch(
            route('profile.income.update'),
            {
                monthly_income: '',
                income_type: 'net',
                income_currency: 'EUR',
            },
            { preserveScroll: true },
        );
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Monthly income
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    Set your net or brutto (gross) monthly income so budgets and
                    spending can be compared against what you earn. For bonuses,
                    freelance payments, and other non-recurring earnings,{' '}
                    <Link
                        href={route('incomes.index')}
                        className="font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        record one-time income
                    </Link>
                    .
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="monthly_income"
                            value="Amount (per month)"
                        />
                        <TextInput
                            id="monthly_income"
                            type="number"
                            min="0"
                            step="0.01"
                            className="mt-1 block w-full"
                            value={data.monthly_income}
                            onChange={(e) =>
                                setData('monthly_income', e.target.value)
                            }
                            placeholder="e.g. 3500"
                        />
                        <InputError
                            className="mt-2"
                            message={errors.monthly_income}
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="income_type" value="Income type" />
                        <select
                            id="income_type"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.income_type}
                            onChange={(e) =>
                                setData('income_type', e.target.value)
                            }
                        >
                            {incomeTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.income_type}
                        />
                    </div>
                </div>

                <div className="max-w-xs">
                    <InputLabel htmlFor="income_currency" value="Currency" />
                    <select
                        id="income_currency"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.income_currency}
                        onChange={(e) =>
                            setData('income_currency', e.target.value)
                        }
                    >
                        {['EUR', 'USD', 'GBP', 'INR', 'PKR', 'TRY'].map(
                            (code) => (
                                <option key={code} value={code}>
                                    {code}
                                </option>
                            ),
                        )}
                    </select>
                    <InputError
                        className="mt-2"
                        message={errors.income_currency}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <PrimaryButton
                        icon={CheckIcon}
                        iconOnly
                        disabled={processing}
                    >
                        Save income
                    </PrimaryButton>

                    {data.monthly_income !== '' && (
                        <button
                            type="button"
                            onClick={clearIncome}
                            className="text-sm text-gray-600 underline hover:text-gray-900"
                        >
                            Clear income
                        </button>
                    )}

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
