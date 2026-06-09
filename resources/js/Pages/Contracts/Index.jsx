import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline';
import { formatCurrency } from '@/utils/money';

const statusBadge = {
    active: 'bg-green-100 text-green-800',
    paused: 'bg-yellow-100 text-yellow-800',
    cancelled: 'bg-gray-200 text-gray-600',
};

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString('de-DE', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';

const isOverdue = (value) =>
    value && new Date(value) < new Date(new Date().toDateString());

function SummaryCard({ label, value }) {
    return (
        <div className="rounded-lg bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className="mt-1 text-2xl font-semibold text-gray-900">
                {value}
            </div>
        </div>
    );
}

export default function Index({ contracts, summary }) {
    const flash = usePage().props.flash ?? {};

    const groups = contracts.reduce((acc, contract) => {
        const key = contract.category?.name ?? 'Uncategorized';
        (acc[key] ||= []).push(contract);
        return acc;
    }, {});

    const handleDelete = (contract) => {
        if (window.confirm(`Delete contract "${contract.name}"?`)) {
            router.delete(route('contracts.destroy', contract.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Monthly Contracts
                    </h2>
                    <Link href={route('contracts.create')}>
                        <PrimaryButton icon={PlusIcon}>
                            Add Contract
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Contracts" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <SummaryCard
                            label="Fixed cost / month"
                            value={formatCurrency(summary.monthly_total)}
                        />
                        <SummaryCard
                            label="Fixed cost / year"
                            value={formatCurrency(summary.yearly_total)}
                        />
                        <SummaryCard
                            label="Active contracts"
                            value={summary.active_count}
                        />
                    </div>

                    {contracts.length === 0 ? (
                        <div className="rounded-lg bg-white p-10 text-center shadow-sm">
                            <p className="text-gray-500">
                                No contracts yet. Add your recurring expenses
                                (rent, internet, subscriptions…) to see your
                                fixed monthly cost.
                            </p>
                            <div className="mt-4 flex justify-center">
                                <Link href={route('contracts.create')}>
                                    <PrimaryButton icon={PlusIcon}>
                                        Add your first contract
                                    </PrimaryButton>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        Object.entries(groups).map(([category, items]) => {
                            const groupMonthly = items
                                .filter((c) => c.status === 'active')
                                .reduce(
                                    (sum, c) =>
                                        sum +
                                        Number(c.projected_monthly_amount),
                                    0,
                                );

                            return (
                                <div
                                    key={category}
                                    className="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                                >
                                    <div className="flex items-center justify-between border-b border-gray-100 px-6 py-3">
                                        <h3 className="font-semibold text-gray-700">
                                            {category}
                                        </h3>
                                        <span className="text-sm text-gray-500">
                                            {formatCurrency(groupMonthly)} / mo
                                        </span>
                                    </div>
                                    <ul className="divide-y divide-gray-100">
                                        {items.map((contract) => (
                                            <li
                                                key={contract.id}
                                                className="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0">
                                                    <Link
                                                        href={route(
                                                            'contracts.show',
                                                            contract.id,
                                                        )}
                                                        className="font-medium text-gray-900 hover:underline"
                                                    >
                                                        {contract.name}
                                                    </Link>
                                                    <div className="mt-0.5 text-sm text-gray-500">
                                                        {contract.provider
                                                            ?.name ??
                                                            'No provider'}{' '}
                                                        ·{' '}
                                                        {contract.billing_cycle}
                                                    </div>
                                                    <div className="mt-1 flex flex-wrap items-center gap-2">
                                                        <span
                                                            className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${statusBadge[contract.status] ?? 'bg-gray-100 text-gray-700'}`}
                                                        >
                                                            {contract.status}
                                                        </span>
                                                        <span
                                                            className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${isOverdue(contract.next_billing_date) && contract.status === 'active' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600'}`}
                                                        >
                                                            Next:{' '}
                                                            {formatDate(
                                                                contract.next_billing_date,
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="flex items-center gap-6">
                                                    <div className="text-right">
                                                        <div className="font-semibold text-gray-900">
                                                            {formatCurrency(
                                                                contract.amount,
                                                                contract.currency,
                                                            )}
                                                        </div>
                                                        <div className="text-xs text-gray-500">
                                                            {formatCurrency(
                                                                contract.projected_monthly_amount,
                                                                contract.currency,
                                                            )}{' '}
                                                            / mo
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Link
                                                            href={route(
                                                                'contracts.edit',
                                                                contract.id,
                                                            )}
                                                            className="text-gray-400 hover:text-gray-700"
                                                            title="Edit"
                                                        >
                                                            <PencilSquareIcon className="h-5 w-5" />
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    contract,
                                                                )
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
                            );
                        })
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
