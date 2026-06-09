import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { PencilSquareIcon } from '@heroicons/react/24/outline';
import { formatCurrency } from '@/utils/money';

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('de-DE') : '—';

function Row({ label, value }) {
    return (
        <div className="flex justify-between border-b border-gray-100 py-3">
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="text-sm text-gray-900">{value}</dd>
        </div>
    );
}

export default function Show({ contract }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {contract.name}
                    </h2>
                    <Link href={route('contracts.edit', contract.id)}>
                        <PrimaryButton icon={PencilSquareIcon}>
                            Edit
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title={contract.name} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <dl>
                            <Row
                                label="Amount"
                                value={`${formatCurrency(contract.amount, contract.currency)} · ${contract.billing_cycle}`}
                            />
                            <Row
                                label="Projected monthly"
                                value={formatCurrency(
                                    contract.projected_monthly_amount,
                                    contract.currency,
                                )}
                            />
                            <Row
                                label="Provider"
                                value={contract.provider?.name ?? '—'}
                            />
                            <Row
                                label="Category"
                                value={contract.category?.name ?? '—'}
                            />
                            <Row label="Status" value={contract.status} />
                            <Row
                                label="Start date"
                                value={formatDate(contract.start_date)}
                            />
                            <Row
                                label="End date"
                                value={formatDate(contract.end_date)}
                            />
                            <Row
                                label="Next billing date"
                                value={formatDate(contract.next_billing_date)}
                            />
                            <Row
                                label="Billing day"
                                value={contract.billing_day ?? '—'}
                            />
                            <Row
                                label="Auto-renew"
                                value={contract.auto_renew ? 'Yes' : 'No'}
                            />
                            {contract.description && (
                                <Row
                                    label="Description"
                                    value={contract.description}
                                />
                            )}
                            {contract.notes && (
                                <Row label="Notes" value={contract.notes} />
                            )}
                        </dl>

                        <div className="mt-6">
                            <Link
                                href={route('contracts.index')}
                                className="text-sm text-gray-600 hover:text-gray-900"
                            >
                                ← Back to contracts
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
