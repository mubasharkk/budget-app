import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdjustmentsHorizontalIcon } from '@heroicons/react/24/outline';
import BudgetOverview from '@/Components/BudgetOverview';
import Checkbox from '@/Components/Checkbox';
import DashboardAtAGlance from '@/Components/DashboardAtAGlance';
import ExpenseOverview from '@/Components/ExpenseOverview';
import IncomeOverview from '@/Components/IncomeOverview';
import ConsumedItemsWidget from '@/Components/ConsumedItemsWidget';
import MostBoughtItemsChart from '@/Components/MostBoughtItemsChart';
import TransactionsWidget from '@/Components/TransactionsWidget';
import UpcomingBillsWidget from '@/Components/UpcomingBillsWidget';

const OPTIONAL_SECTIONS = {
    transactions: { Component: TransactionsWidget, half: false },
    upcoming_bills: { Component: UpcomingBillsWidget, half: true },
    expense_overview: { Component: ExpenseOverview, half: false },
    items_consumed: { Component: ConsumedItemsWidget, half: false },
    budget_vs_actual: { Component: BudgetOverview, half: true },
    most_bought_items: { Component: MostBoughtItemsChart, half: true },
};

function Card({ children }) {
    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            {children}
        </div>
    );
}

function SectionPicker({ available, selected, onToggle }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((previous) => !previous)}
                aria-haspopup="true"
                aria-expanded={open}
                className="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-mid focus:ring-offset-2"
            >
                <AdjustmentsHorizontalIcon className="h-5 w-5" />
                Customize
            </button>

            {open && (
                <>
                    <div
                        className="fixed inset-0 z-40"
                        onClick={() => setOpen(false)}
                    />
                    <div className="absolute end-0 z-50 mt-2 w-64 rounded-md bg-white p-3 shadow-lg ring-1 ring-black ring-opacity-5">
                        <div className="px-1 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            Extra sections
                        </div>
                        <div className="flex flex-col gap-1">
                            {available.map((section) => (
                                <label
                                    key={section.value}
                                    className="flex cursor-pointer items-center gap-3 rounded-md px-1 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                                >
                                    <Checkbox
                                        checked={selected.includes(
                                            section.value,
                                        )}
                                        onChange={() =>
                                            onToggle(section.value)
                                        }
                                    />
                                    {section.label}
                                </label>
                            ))}
                        </div>
                        <p className="px-1 pt-2 text-xs text-gray-400">
                            At a glance and Income vs spending are always
                            shown.
                        </p>
                    </div>
                </>
            )}
        </div>
    );
}

export default function Dashboard({ sections = [], availableSections = [] }) {
    const [selected, setSelected] = useState(sections);

    const toggleSection = (value) => {
        const next = selected.includes(value)
            ? selected.filter((section) => section !== value)
            : [...selected, value];

        setSelected(next);
        router.put(
            route('dashboard.settings.update'),
            { sections: next },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setSelected(selected),
            },
        );
    };

    const visible = availableSections
        .map((section) => section.value)
        .filter(
            (value) => selected.includes(value) && OPTIONAL_SECTIONS[value],
        );
    const fullWidth = visible.filter((value) => !OPTIONAL_SECTIONS[value].half);
    const halfWidth = visible.filter((value) => OPTIONAL_SECTIONS[value].half);

    const renderSection = (value) => {
        const { Component } = OPTIONAL_SECTIONS[value];

        return (
            <Card key={value}>
                <Component />
            </Card>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Dashboard
                    </h2>
                    <SectionPicker
                        available={availableSections}
                        selected={selected}
                        onToggle={toggleSection}
                    />
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <Card>
                        <DashboardAtAGlance />
                    </Card>

                    <Card>
                        <IncomeOverview />
                    </Card>

                    {fullWidth.map(renderSection)}

                    {halfWidth.length > 0 && (
                        <div
                            className={`grid gap-6 ${halfWidth.length > 1 ? 'lg:grid-cols-2' : ''}`}
                        >
                            {halfWidth.map(renderSection)}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
