import { useState } from 'react';
import { Deferred } from '@inertiajs/react';
import CheapestVendorsPanel from '@/Components/Deals/CheapestVendorsPanel';
import DateRangeFilter from '@/Components/Deals/DateRangeFilter';
import PriceTrendsPanel from '@/Components/Deals/PriceTrendsPanel';
import SavingsPanel from '@/Components/Deals/SavingsPanel';
import { showPrices } from '@/Components/Deals/visitDeals';

const EMPTY_RANGE = { start_date: '', end_date: '' };

const toNumbers = (rows, keys) =>
    rows.map((row) => ({ ...row, ...Object.fromEntries(keys.map((key) => [key, Number(row[key])])) }));

function PricePanels({ prices }) {
    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <SavingsPanel
                rows={toNumbers(prices.savings_opportunities, ['paid_price', 'cheapest_price', 'potential_savings', 'quantity'])}
            />
            <CheapestVendorsPanel rows={toNumbers(prices.cheapest_vendors, ['cheapest_price', 'max_price', 'vendor_count'])} />
            <PriceTrendsPanel
                rows={toNumbers(prices.price_trends, ['latest_price', 'previous_price', 'change', 'change_percent'])}
            />
        </div>
    );
}

export default function MyPricesTab({ range: appliedRange, prices }) {
    const [range, setRange] = useState(appliedRange);

    return (
        <div className="flex flex-col gap-6">
            <p className="text-sm text-gray-600">
                Compare what you paid against your cheapest observed prices. Products are matched
                automatically when receipts are processed.
            </p>
            <DateRangeFilter
                range={range}
                onChange={setRange}
                onApply={() => showPrices(range)}
                onClear={() => {
                    setRange(EMPTY_RANGE);
                    showPrices(EMPTY_RANGE);
                }}
            />
            <Deferred data="prices" fallback={<div className="h-72 animate-pulse rounded-lg bg-gray-200" />}>
                {prices && <PricePanels prices={prices} />}
            </Deferred>
        </div>
    );
}
