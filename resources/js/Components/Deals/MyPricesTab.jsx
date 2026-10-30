import { useEffect, useState } from 'react';
import axios from 'axios';
import CheapestVendorsPanel from '@/Components/Deals/CheapestVendorsPanel';
import DateRangeFilter from '@/Components/Deals/DateRangeFilter';
import PriceTrendsPanel from '@/Components/Deals/PriceTrendsPanel';
import SavingsPanel from '@/Components/Deals/SavingsPanel';

const EMPTY_RANGE = { start_date: '', end_date: '' };

const toNumbers = (rows, keys) =>
    rows.map((row) => ({ ...row, ...Object.fromEntries(keys.map((key) => [key, Number(row[key])])) }));

const normalize = (data) => ({
    savings: toNumbers(data.savings_opportunities, ['paid_price', 'cheapest_price', 'potential_savings', 'quantity']),
    cheapest: toNumbers(data.cheapest_vendors, ['cheapest_price', 'max_price', 'vendor_count']),
    trends: toNumbers(data.price_trends, ['latest_price', 'previous_price', 'change', 'change_percent']),
});

export default function MyPricesTab() {
    const [range, setRange] = useState(EMPTY_RANGE);
    const [appliedRange, setAppliedRange] = useState(EMPTY_RANGE);
    const [data, setData] = useState(null);

    useEffect(() => {
        setData(null);
        const params = Object.fromEntries(Object.entries(appliedRange).filter(([, value]) => value));

        axios
            .get(route('dashboard.deals'), { params })
            .then((response) => setData(normalize(response.data)))
            .catch(() => setData(normalize({ savings_opportunities: [], cheapest_vendors: [], price_trends: [] })));
    }, [appliedRange]);

    return (
        <div className="flex flex-col gap-6">
            <p className="text-sm text-gray-600">
                Compare what you paid against your cheapest observed prices. Products are matched
                automatically when receipts are processed.
            </p>
            <DateRangeFilter
                range={range}
                onChange={setRange}
                onApply={() => setAppliedRange(range)}
                onClear={() => {
                    setRange(EMPTY_RANGE);
                    setAppliedRange(EMPTY_RANGE);
                }}
            />
            {data === null ? (
                <div className="h-72 animate-pulse rounded-lg bg-gray-200" />
            ) : (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <SavingsPanel rows={data.savings} />
                    <CheapestVendorsPanel rows={data.cheapest} />
                    <PriceTrendsPanel rows={data.trends} />
                </div>
            )}
        </div>
    );
}
