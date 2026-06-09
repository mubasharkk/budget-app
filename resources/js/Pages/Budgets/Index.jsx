import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline';
import { formatCurrency } from '@/utils/money';

const statusStyles = {
    on_track: { bar: 'bg-green-500', badge: 'bg-green-100 text-green-800', label: 'On track' },
    warning: { bar: 'bg-amber-500', badge: 'bg-amber-100 text-amber-800', label: 'Near limit' },
    over: { bar: 'bg-red-500', badge: 'bg-red-100 text-red-800', label: 'Over budget' },
};

function SummaryCard({ label, value, accent = 'text-gray-900' }) {
    return (
        <div className="rounded-lg bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className={`mt-1 text-2xl font-semibold ${accent}`}>
                {value}
            </div>
        </div>
    );
}

function ProgressRow({ item }) {
    const style = statusStyles[item.status] ?? statusStyles.on_track;
    const percent = Math.min(item.percent_used, 100);

    return (
        <div className="rounded-lg border border-gray-100 p-4">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <div className="font-medium text-gray-900">{item.label}</div>
                    <div className="mt-0.5 text-sm text-gray-500">
                        {formatCurrency(item.actual, item.currency)} of{' '}
                        {formatCurrency(item.budget_amount, item.currency)}
                        {item.fixed > 0 && (
                            <span className="ml-1 text-gray-400">
                                (fixed {formatCurrency(item.fixed)} + variable{' '}
                                {formatCurrency(item.variable)})
                            </span>
                        )}
                    </div>
                </div>
                <span
                    className={`inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ${style.badge}`}
                >
                    {style.label}
                </span>
            </div>

            <div className="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                <div
                    className={`h-full transition-all ${style.bar}`}
                    style={{ width: `${percent}%` }}
                />
            </div>

            <div className="mt-2 flex justify-between text-xs text-gray-500">
                <span>{item.percent_used}% used</span>
                <span>
                    Projected {formatCurrency(item.projected, item.currency)} (
                    {item.projected_percent}%)
                </span>
            </div>
        </div>
    );
}

export default function Index({ period, summary, budgets }) {
    const flash = usePage().props.flash ?? {};
    const progressById = Object.fromEntries(
        (summary.items ?? []).map((item) => [item.budget_id, item]),
    );

    const switchPeriod = (next) => {
        router.get(route('budgets.index'), { period: next }, { preserveState: true });
    };

    const handleDelete = (budget) => {
        const label = budget.category?.name ?? 'All categories';
        if (window.confirm(`Delete budget for "${label}"?`)) {
            router.delete(route('budgets.destroy', budget.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Budgets
                    </h2>
                    <Link href={route('budgets.create')}>
                        <PrimaryButton icon={PlusIcon}>Add Budget</PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Budgets" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-600">
                            Track spending against limits. Actuals include both
                            fixed contracts and variable receipts.
                        </p>
                        <div className="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
                            {[
                                { key: 'monthly', label: 'Month' },
                                { key: 'weekly', label: 'Week' },
                            ].map((p) => (
                                <button
                                    key={p.key}
                                    type="button"
                                    onClick={() => switchPeriod(p.key)}
                                    className={`rounded px-3 py-1 text-sm font-medium transition ${
                                        period === p.key
                                            ? 'bg-gray-800 text-white'
                                            : 'text-gray-600 hover:text-gray-900'
                                    }`}
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <SummaryCard
                            label="Budgeted"
                            value={formatCurrency(summary.budgeted)}
                        />
                        <SummaryCard
                            label="Actual spend"
                            value={formatCurrency(summary.actual)}
                        />
                        <SummaryCard
                            label="Remaining"
                            value={formatCurrency(summary.remaining)}
                            accent="text-emerald-600"
                        />
                        <SummaryCard
                            label="Alerts"
                            value={`${summary.over_count} over · ${summary.warning_count} near`}
                            accent={
                                summary.over_count > 0
                                    ? 'text-red-600'
                                    : 'text-gray-900'
                            }
                        />
                    </div>

                    {budgets.length === 0 ? (
                        <div className="rounded-lg bg-white p-10 text-center shadow-sm">
                            <p className="text-gray-500">
                                No budgets set for this period. Add a per-category
                                or overall budget to start tracking limits.
                            </p>
                            <div className="mt-4 flex justify-center">
                                <Link href={route('budgets.create')}>
                                    <PrimaryButton icon={PlusIcon}>
                                        Add Budget
                                    </PrimaryButton>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {budgets.map((budget) => {
                                const progress = progressById[budget.id];
                                const label =
                                    budget.category?.name ?? 'All categories';

                                return (
                                    <div
                                        key={budget.id}
                                        className="overflow-hidden rounded-lg bg-white shadow-sm"
                                    >
                                        {progress ? (
                                            <div className="p-4">
                                                <ProgressRow item={progress} />
                                            </div>
                                        ) : (
                                            <div className="p-4 text-sm text-gray-500">
                                                {label} — starts{' '}
                                                {new Date(
                                                    budget.starts_on,
                                                ).toLocaleDateString()}
                                            </div>
                                        )}

                                        <div className="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-4 py-2">
                                            <div className="text-sm text-gray-600">
                                                Limit:{' '}
                                                {formatCurrency(
                                                    budget.amount,
                                                    budget.currency,
                                                )}{' '}
                                                / {budget.period}
                                            </div>
                                            <div className="flex gap-2">
                                                <Link
                                                    href={route(
                                                        'budgets.edit',
                                                        budget.id,
                                                    )}
                                                    className="rounded p-1 text-gray-400 hover:bg-white hover:text-indigo-600"
                                                >
                                                    <PencilSquareIcon className="h-5 w-5" />
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleDelete(budget)
                                                    }
                                                    className="rounded p-1 text-gray-400 hover:bg-white hover:text-red-600"
                                                >
                                                    <TrashIcon className="h-5 w-5" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
