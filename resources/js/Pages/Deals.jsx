import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/utils/money';

const toNumber = (rows, keys) =>
    rows.map((row) => {
        const out = { ...row };
        keys.forEach((k) => (out[k] = Number(out[k])));
        return out;
    });

function Panel({ title, children }) {
    return (
        <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="mb-4 text-base font-medium text-gray-900">{title}</h3>
            {children}
        </div>
    );
}

function EmptyState({ children }) {
    return (
        <p className="py-12 text-center text-sm text-gray-400">{children}</p>
    );
}

function TrendBadge({ direction }) {
    const styles = {
        rising: 'bg-red-100 text-red-700',
        falling: 'bg-green-100 text-green-700',
        stable: 'bg-gray-100 text-gray-600',
    };

    const labels = {
        rising: 'Rising',
        falling: 'Falling',
        stable: 'Stable',
    };

    return (
        <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${styles[direction] ?? styles.stable}`}
        >
            {labels[direction] ?? direction}
        </span>
    );
}

export default function Deals() {
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchData = (params = {}) => {
        setLoading(true);
        const query = { ...params };
        if (startDate) query.start_date = startDate;
        if (endDate) query.end_date = endDate;

        axios
            .get('/dashboard/deals', { params: query })
            .then((res) => setData(res.data))
            .catch((error) =>
                console.error('Error fetching deals data:', error),
            )
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        fetchData();
    }, []);

    const clearFilters = () => {
        setStartDate('');
        setEndDate('');
        setLoading(true);
        axios
            .get('/dashboard/deals')
            .then((res) => setData(res.data))
            .finally(() => setLoading(false));
    };

    const opportunities = data
        ? toNumber(data.savings_opportunities, [
              'paid_price',
              'cheapest_price',
              'potential_savings',
              'quantity',
          ])
        : [];
    const cheapest = data
        ? toNumber(data.cheapest_vendors, [
              'cheapest_price',
              'max_price',
              'vendor_count',
          ])
        : [];
    const trends = data
        ? toNumber(data.price_trends, [
              'latest_price',
              'previous_price',
              'change',
              'change_percent',
          ])
        : [];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Deals
                </h2>
            }
        >
            <Head title="Deals" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <p className="text-sm text-gray-600">
                            Compare what you paid against your cheapest observed
                            prices. Products are matched automatically when
                            receipts are processed.
                        </p>
                    </div>

                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel htmlFor="start" value="From" />
                                <TextInput
                                    id="start"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={startDate}
                                    onChange={(e) =>
                                        setStartDate(e.target.value)
                                    }
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
                            <div className="flex items-end gap-2">
                                <PrimaryButton
                                    type="button"
                                    onClick={() => fetchData()}
                                >
                                    Apply
                                </PrimaryButton>
                                <SecondaryButton
                                    type="button"
                                    onClick={clearFilters}
                                >
                                    Clear
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>

                    {loading ? (
                        <div className="h-72 animate-pulse rounded-lg bg-gray-200" />
                    ) : (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Panel title="Potential savings">
                                {opportunities.length === 0 ? (
                                    <EmptyState>
                                        No overpayments detected yet. Upload more
                                        receipts from different vendors to compare
                                        prices.
                                    </EmptyState>
                                ) : (
                                    <ul className="divide-y divide-gray-100">
                                        {opportunities.map((row) => (
                                            <li
                                                key={row.receipt_item_id}
                                                className="py-3"
                                            >
                                                <div className="flex items-start justify-between gap-4">
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={route(
                                                                'products.show',
                                                                row.product_id,
                                                            )}
                                                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            {row.product_name}
                                                        </Link>
                                                        <p className="mt-0.5 text-xs text-gray-500">
                                                            Paid{' '}
                                                            {formatCurrency(
                                                                row.paid_price,
                                                                row.currency,
                                                            )}{' '}
                                                            at {row.vendor} ·
                                                            cheapest{' '}
                                                            {formatCurrency(
                                                                row.cheapest_price,
                                                                row.currency,
                                                            )}
                                                            {row.cheapest_vendor
                                                                ? ` at ${row.cheapest_vendor}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                    <div className="shrink-0 text-right">
                                                        <div className="text-sm font-semibold text-green-700">
                                                            +
                                                            {formatCurrency(
                                                                row.potential_savings,
                                                                row.currency,
                                                            )}
                                                        </div>
                                                        <div className="text-xs text-gray-400">
                                                            ×{row.quantity}
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>

                            <Panel title="Cheapest vendor per product">
                                {cheapest.length === 0 ? (
                                    <EmptyState>
                                        No product prices recorded yet.
                                    </EmptyState>
                                ) : (
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-gray-500">
                                                <th className="pb-2 font-medium">
                                                    Product
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Vendor
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Best price
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {cheapest.map((row) => (
                                                <tr key={row.product_id}>
                                                    <td className="py-2">
                                                        <Link
                                                            href={route(
                                                                'products.show',
                                                                row.product_id,
                                                            )}
                                                            className="text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            {row.product_name}
                                                        </Link>
                                                    </td>
                                                    <td className="py-2 text-gray-600">
                                                        {row.cheapest_vendor ??
                                                            '—'}
                                                    </td>
                                                    <td className="py-2 text-right font-medium text-gray-900">
                                                        {formatCurrency(
                                                            row.cheapest_price,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </Panel>

                            <Panel title="Recent price trends">
                                {trends.length === 0 ? (
                                    <EmptyState>
                                        Need at least two price observations per
                                        product to show trends.
                                    </EmptyState>
                                ) : (
                                    <ul className="divide-y divide-gray-100">
                                        {trends.map((row) => (
                                            <li
                                                key={row.product_id}
                                                className="flex items-center justify-between py-2.5"
                                            >
                                                <div>
                                                    <Link
                                                        href={route(
                                                            'products.show',
                                                            row.product_id,
                                                        )}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        {row.product_name}
                                                    </Link>
                                                    <div className="mt-0.5 text-xs text-gray-500">
                                                        {formatCurrency(
                                                            row.previous_price,
                                                        )}{' '}
                                                        →{' '}
                                                        {formatCurrency(
                                                            row.latest_price,
                                                        )}
                                                        {row.change_percent !=
                                                            null && (
                                                            <span className="ml-1">
                                                                (
                                                                {row.change_percent >
                                                                0
                                                                    ? '+'
                                                                    : ''}
                                                                {
                                                                    row.change_percent
                                                                }
                                                                %)
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                                <TrendBadge
                                                    direction={row.direction}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
