import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import BudgetOverview from '@/Components/BudgetOverview';
import DashboardAtAGlance from '@/Components/DashboardAtAGlance';
import ExpenseOverview from '@/Components/ExpenseOverview';
import IncomeOverview from '@/Components/IncomeOverview';
import MostBoughtItemsChart from '@/Components/MostBoughtItemsChart';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <DashboardAtAGlance />
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <IncomeOverview />
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <ExpenseOverview />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <BudgetOverview />
                        </div>
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <MostBoughtItemsChart />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
