import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline';
import { formatCurrency } from '@/utils/money';

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString('de-DE', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';

export default function Index({ savings, summary }) {
    const flash = usePage().props.flash ?? {};

    const handleDelete = (saving) => {
        const label = saving.source || 'this savings entry';
        if (window.confirm(`Delete ${label}?`)) {
            router.delete(route('savings.destroy', saving.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Savings
                    </h2>
                    <Link href={route('savings.create')}>
                        <PrimaryButton icon={PlusIcon}>
                            Add Savings
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Savings" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">
                                Total saved
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

                    {savings.length === 0 ? (
                        <div className="rounded-lg bg-white p-10 text-center shadow-sm">
                            <p className="text-gray-500">
                                No savings recorded yet. Track money you set aside
                                — emergency fund, vacation, investments — here.
                                Savings are kept separate from your balance.
                            </p>
                            <div className="mt-4 flex justify-center">
                                <Link href={route('savings.create')}>
                                    <PrimaryButton icon={PlusIcon}>
                                        Add your first savings
                                    </PrimaryButton>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <ul className="divide-y divide-gray-100">
                                {savings.map((saving) => (
                                    <li
                                        key={saving.id}
                                        className="flex items-center justify-between px-6 py-4"
                                    >
                                        <div className="min-w-0">
                                            <div className="font-medium text-gray-900">
                                                {saving.source || 'Savings'}
                                            </div>
                                            <div className="mt-0.5 text-sm text-gray-500">
                                                {formatDate(saving.saved_on)}
                                            </div>
                                            {saving.notes && (
                                                <div className="mt-1 text-sm text-gray-400">
                                                    {saving.notes}
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <div className="text-right font-semibold text-gray-900">
                                                {formatCurrency(
                                                    saving.amount,
                                                    saving.currency,
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Link
                                                    href={route(
                                                        'savings.edit',
                                                        saving.id,
                                                    )}
                                                    className="text-gray-400 hover:text-gray-700"
                                                    title="Edit"
                                                >
                                                    <PencilSquareIcon className="h-5 w-5" />
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleDelete(saving)
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
        </AuthenticatedLayout>
    );
}
