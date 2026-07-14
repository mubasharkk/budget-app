import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    PaperClipIcon,
} from '@heroicons/react/24/outline';
import { formatCurrency } from '@/utils/money';

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString('de-DE', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';

const typeBadge = (type) =>
    type === 'business'
        ? 'bg-blue-50 text-blue-700'
        : 'bg-gray-100 text-gray-600';

export default function Index({ expenses, summary }) {
    const flash = usePage().props.flash ?? {};

    const handleDelete = (expense) => {
        const label = expense.description || 'this expense';
        if (window.confirm(`Delete ${label}?`)) {
            router.delete(route('expenses.destroy', expense.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        One-time Expenses
                    </h2>
                    <Link href={route('expenses.create')}>
                        <PrimaryButton icon={PlusIcon}>
                            Add Expense
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Expenses" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                                Personal
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900">
                                {formatCurrency(summary.personal)}
                            </div>
                        </div>
                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">
                                Business
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-blue-700">
                                {formatCurrency(summary.business)}
                            </div>
                        </div>
                    </div>

                    {expenses.length === 0 ? (
                        <div className="rounded-lg bg-white p-10 text-center shadow-sm">
                            <p className="text-gray-500">
                                No one-time expenses yet. Record ad-hoc costs
                                that don't come from a receipt or a recurring
                                contract — and tag each as personal or business.
                                They're tracked as their own ledger, separate
                                from your dashboard spending totals.
                            </p>
                            <div className="mt-4 flex justify-center">
                                <Link href={route('expenses.create')}>
                                    <PrimaryButton icon={PlusIcon}>
                                        Add your first expense
                                    </PrimaryButton>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <ul className="divide-y divide-gray-100">
                                {expenses.map((expense) => (
                                    <li
                                        key={expense.id}
                                        className="flex items-center justify-between px-6 py-4"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-gray-900">
                                                    {expense.description ||
                                                        'One-time expense'}
                                                </span>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${typeBadge(
                                                        expense.expense_type,
                                                    )}`}
                                                >
                                                    {expense.expense_type}
                                                </span>
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-2 text-sm text-gray-500">
                                                {formatDate(expense.spent_on)}
                                                {expense.document && (
                                                    <a
                                                        href={expense.document.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800"
                                                        title={expense.document.name}
                                                    >
                                                        <PaperClipIcon className="h-4 w-4" />
                                                        Invoice
                                                    </a>
                                                )}
                                            </div>
                                            {expense.notes && (
                                                <div className="mt-1 text-sm text-gray-400">
                                                    {expense.notes}
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <div className="text-right font-semibold text-gray-900">
                                                {formatCurrency(
                                                    expense.amount,
                                                    expense.currency,
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Link
                                                    href={route(
                                                        'expenses.edit',
                                                        expense.id,
                                                    )}
                                                    className="text-gray-400 hover:text-gray-700"
                                                    title="Edit"
                                                >
                                                    <PencilSquareIcon className="h-5 w-5" />
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleDelete(expense)
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
