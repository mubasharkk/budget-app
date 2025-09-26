import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
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
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="mb-6">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="p-6 text-gray-900">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Welcome to your Budget App Dashboard</h3>
                                <p className="text-gray-600">
                                    Track your spending patterns and analyze your most purchased items with interactive charts.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <MostBoughtItemsChart />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
