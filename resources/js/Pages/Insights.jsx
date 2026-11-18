import { useEffect, useState } from 'react';
import axios from 'axios';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import ConsumedItemList from '@/Components/Insights/ConsumedItemList';
import ConsumptionAnalysisCard from '@/Components/Insights/ConsumptionAnalysisCard';
import ConsumptionScopePanel from '@/Components/Insights/ConsumptionScopePanel';
import MonthlySpendChart from '@/Components/Insights/MonthlySpendChart';
import { formatCurrency } from '@/utils/money';

const CURRENT_YEAR = new Date().getFullYear();
const YEAR_OPTIONS = Array.from({ length: 5 }, (_, i) => CURRENT_YEAR - i);

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

function Panel({ title, children }) {
    return (
        <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="mb-4 text-base font-medium text-gray-900">{title}</h3>
            {children}
        </div>
    );
}

function EmptyState({ children }) {
    return <p className="py-12 text-center text-sm text-gray-400">{children}</p>;
}

export default function Insights() {
    const [categoryOptions, setCategoryOptions] = useState([]);
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [year, setYear] = useState(CURRENT_YEAR);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchData = (overrides = {}) => {
        setLoading(true);
        const params = {
            year,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            category_id: categoryId || undefined,
            ...overrides,
        };

        axios
            .get(route('dashboard.consumption'), { params })
            .then((res) => setData(res.data))
            .catch((error) => console.error('Error fetching consumption data:', error))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        axios
            .get(route('dashboard.categories'))
            .then((res) => setCategoryOptions(res.data.categories))
            .catch(() => {});
        fetchData();
    }, []);

    const clearFilters = () => {
        setStartDate('');
        setEndDate('');
        setCategoryId('');
        fetchData({ start_date: undefined, end_date: undefined, category_id: undefined });
    };

    const changeYear = (nextYear) => {
        setYear(nextYear);
        fetchData({ year: nextYear });
    };

    const topByQuantity = data?.top_by_quantity ?? [];
    const topBySpend = data?.top_by_spend ?? [];
    const vendors = data?.vendors ?? [];

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Consumption Insights</h2>}
        >
            <Head title="Insights" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <ConsumptionScopePanel
                        categories={categoryOptions}
                        excludedIds={data?.excluded_category_ids ?? []}
                        includeBusiness={data?.include_business ?? false}
                        onSaved={() => fetchData()}
                    />

                    {!data ? (
                        <div className="h-96 animate-pulse rounded-lg bg-gray-200" />
                    ) : (
                        <MonthlySpendChart
                            series={data.spend_series ?? []}
                            year={data.year ?? year}
                            years={YEAR_OPTIONS}
                            onYearChange={changeYear}
                        />
                    )}

                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="mb-3 text-sm font-medium text-gray-900">Items and shops</h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <InputLabel htmlFor="start" value="From" />
                                <TextInput
                                    id="start"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="end" value="To" />
                                <TextInput
                                    id="end"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="category" value="Category" />
                                <select
                                    id="category"
                                    className={selectClasses}
                                    value={categoryId}
                                    onChange={(e) => setCategoryId(e.target.value)}
                                >
                                    <option value="">All categories</option>
                                    {categoryOptions.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-end gap-2">
                                <PrimaryButton type="button" onClick={() => fetchData()}>
                                    Apply
                                </PrimaryButton>
                                <SecondaryButton type="button" onClick={clearFilters}>
                                    Clear
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>

                    {loading ? (
                        <div className="h-72 animate-pulse rounded-lg bg-gray-200" />
                    ) : (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Panel title="Most consumed">
                                {topByQuantity.length === 0 ? (
                                    <EmptyState>No items in this range.</EmptyState>
                                ) : (
                                    <ConsumedItemList items={topByQuantity} />
                                )}
                            </Panel>

                            <Panel title="Top spend (by item)">
                                {topBySpend.length === 0 ? (
                                    <EmptyState>No items in this range.</EmptyState>
                                ) : (
                                    <ConsumedItemList items={topBySpend} />
                                )}
                            </Panel>

                            <Panel title="Where you shop for it">
                                {vendors.length === 0 ? (
                                    <EmptyState>No shops in this range.</EmptyState>
                                ) : (
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-gray-500">
                                                <th className="pb-2 font-medium">Shop</th>
                                                <th className="pb-2 text-right font-medium">Trips</th>
                                                <th className="pb-2 text-right font-medium">Spent on consumption</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {vendors.map((v) => (
                                                <tr key={v.vendor}>
                                                    <td className="py-2 text-gray-900">{v.vendor}</td>
                                                    <td className="py-2 text-right tabular-nums text-gray-600">
                                                        {v.receipt_count}
                                                    </td>
                                                    <td className="py-2 text-right font-medium tabular-nums text-gray-900">
                                                        {formatCurrency(v.total_spent)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </Panel>
                        </div>
                    )}

                    <ConsumptionAnalysisCard />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
