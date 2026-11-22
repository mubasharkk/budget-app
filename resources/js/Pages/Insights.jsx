import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SecondaryButton from '@/Components/SecondaryButton';
import ChangeBreakdown from '@/Components/Insights/ChangeBreakdown';
import ConsumedItemsTable from '@/Components/Insights/ConsumedItemsTable';
import ConsumptionAnalysisCard from '@/Components/Insights/ConsumptionAnalysisCard';
import ConsumptionScopePanel from '@/Components/Insights/ConsumptionScopePanel';
import InsightsFilters from '@/Components/Insights/InsightsFilters';
import InsightsHeadline from '@/Components/Insights/InsightsHeadline';
import MonthlySpendChart from '@/Components/Insights/MonthlySpendChart';
import ShopBreakdown from '@/Components/Insights/ShopBreakdown';
import { formatDay } from '@/Components/Insights/insightFormat';
import { formatCurrencyIn } from '@/utils/money';

const DEFAULT_FILTERS = { period: 'this_month' };
const FILTERS_KEY = 'insights.filters';

/** The last filters, so coming back from a product's page keeps the period. */
const rememberedFilters = () => {
    try {
        return JSON.parse(sessionStorage.getItem(FILTERS_KEY)) ?? DEFAULT_FILTERS;
    } catch {
        return DEFAULT_FILTERS;
    }
};

export default function Insights() {
    const [categories, setCategories] = useState([]);
    const [filters, setFilters] = useState(rememberedFilters);
    const [loaded, setLoaded] = useState(null);
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState(null);
    const latestRequest = useRef(0);

    const load = (params = filters) => {
        const request = ++latestRequest.current;
        const isLatest = () => request === latestRequest.current;
        setLoading(true);
        setFailure(null);

        axios
            .get(route('insights.overview'), { params })
            .then((res) => {
                if (!isLatest()) {
                    return;
                }
                setLoaded({ data: res.data, filters: params });
                try {
                    sessionStorage.setItem(FILTERS_KEY, JSON.stringify(params));
                } catch {
                    // Storage can be blocked; the period is then just not remembered.
                }
            })
            .catch((error) => {
                if (!isLatest()) {
                    return;
                }
                if (error.response?.status === 422 && !loaded) {
                    setFilters(DEFAULT_FILTERS);
                    load(DEFAULT_FILTERS);
                } else {
                    setFailure(error.response?.data?.message ?? 'Your insights could not be loaded.');
                }
            })
            .finally(() => isLatest() && setLoading(false));
    };

    useEffect(() => {
        axios
            .get(route('dashboard.categories'))
            .then((res) => setCategories(res.data.categories))
            .catch(() => {});
        load();
    }, []);

    const changeFilters = (next) => {
        setFilters(next);
        load(next);
    };

    const selectMonth = (month) =>
        changeFilters({
            ...(filters.category_id ? { category_id: filters.category_id } : {}),
            ...(month.is_current ? { period: 'this_month' } : { start_date: month.start, end_date: month.end }),
        });

    const data = loaded?.data;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Consumption Insights</h2>}
        >
            <Head title="Insights" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
                    {failure && (
                        <div className="flex items-center justify-between gap-3 rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                            {failure}
                            <SecondaryButton type="button" onClick={() => load()}>
                                Try again
                            </SecondaryButton>
                        </div>
                    )}

                    {!data && loading && (
                        <div className="space-y-5" aria-label="Loading insights">
                            <div className="h-20 animate-pulse rounded-lg bg-gray-200" />
                            <div className="h-28 animate-pulse rounded-lg bg-gray-200" />
                            <div className="h-96 animate-pulse rounded-lg bg-gray-200" />
                        </div>
                    )}

                    {data && (
                        <>
                            <ConsumptionScopePanel
                                categories={categories}
                                excludedIds={data.scope.excluded_category_ids}
                                includeBusiness={data.scope.include_business}
                                onSaved={() => load()}
                            />

                            <InsightsFilters
                                filters={filters}
                                periods={data.periods}
                                categories={categories}
                                appliedWindow={data.window}
                                onChange={changeFilters}
                            />

                            <div className={`space-y-5 transition-opacity ${loading ? 'opacity-50' : ''}`} aria-busy={loading}>
                                {data.other_currencies.length > 0 && (
                                    <p className="bg-amber-50 p-3 text-sm text-amber-900 sm:rounded-md">
                                        Not counted below, because it is in another currency:{' '}
                                        <strong>
                                            {data.other_currencies
                                                .map((row) => formatCurrencyIn(row.spend, row.currency))
                                                .join(', ')}
                                        </strong>
                                        . If a receipt has the wrong currency, correct it on the receipt.
                                    </p>
                                )}

                                {data.records_start && (
                                    <p className="bg-amber-50 p-3 text-sm text-amber-900 sm:rounded-md">
                                        Your records start on <strong>{formatDay(data.records_start, true)}</strong>, partway
                                        through the earlier period, so the change looks bigger than it really is.
                                    </p>
                                )}

                                <InsightsHeadline totals={data.totals} />
                                <MonthlySpendChart chart={data.chart} onSelectMonth={selectMonth} />
                                <ChangeBreakdown changes={data.changes} change={data.totals.change} />

                                <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
                                    <div className="min-w-0 lg:col-span-2">
                                        <ConsumedItemsTable items={data.items} />
                                    </div>
                                    <ShopBreakdown shops={data.shops} />
                                </div>

                                {data.coverage && (
                                    <p className="px-4 text-xs text-gray-600 sm:px-0">
                                        How complete this is: {data.coverage.named_percent}% of this spend is grouped under a
                                        general name and {data.coverage.measured_percent}% has a known pack size for per-unit
                                        prices.
                                    </p>
                                )}

                                <ConsumptionAnalysisCard
                                    filters={loaded.filters}
                                    appliedWindow={data.window}
                                    spend={data.totals.spend}
                                />
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
