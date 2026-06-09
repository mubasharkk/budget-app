import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    CartesianGrid,
} from 'recharts';
import { formatCurrency } from '@/utils/money';

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

export default function Show({ product }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        axios
            .get(route('products.data', product.id))
            .then((res) => setData(res.data))
            .catch((error) =>
                console.error('Error fetching product data:', error),
            )
            .finally(() => setLoading(false));
    }, [product.id]);

    const chartData = (data?.price_history ?? []).map((row) => ({
        date: row.observed_at,
        price: Number(row.unit_price),
        vendor: row.vendor,
    }));

    const byVendor = (data?.by_vendor ?? []).map((row) => ({
        ...row,
        min_price: Number(row.min_price),
        max_price: Number(row.max_price),
        avg_price: Number(row.avg_price),
        observation_count: Number(row.observation_count),
    }));

    const purchases = (data?.purchases ?? []).map((row) => ({
        ...row,
        quantity: Number(row.quantity),
        unit_price: Number(row.unit_price),
        total: Number(row.total),
    }));

    const meta = [
        product.brand && `Brand: ${product.brand}`,
        product.unit && `Unit: ${product.unit}`,
        product.size && `Size: ${product.size}`,
        product.category?.name,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <Link
                        href={route('savings')}
                        className="text-sm text-gray-500 hover:text-gray-700"
                    >
                        ← Savings
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {product.name}
                    </h2>
                </div>
            }
        >
            <Head title={product.name} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {meta && (
                        <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                            <p className="text-sm text-gray-600">{meta}</p>
                        </div>
                    )}

                    {loading ? (
                        <div className="h-72 animate-pulse rounded-lg bg-gray-200" />
                    ) : (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Panel title="Price over time">
                                {chartData.length === 0 ? (
                                    <EmptyState>
                                        No price observations yet.
                                    </EmptyState>
                                ) : (
                                    <ResponsiveContainer
                                        width="100%"
                                        height={320}
                                    >
                                        <LineChart data={chartData}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis
                                                dataKey="date"
                                                tick={{ fontSize: 12 }}
                                            />
                                            <YAxis
                                                tick={{ fontSize: 12 }}
                                                tickFormatter={(v) =>
                                                    formatCurrency(v)
                                                }
                                            />
                                            <Tooltip
                                                formatter={(value) =>
                                                    formatCurrency(value)
                                                }
                                                labelFormatter={(label) =>
                                                    `Date: ${label}`
                                                }
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="price"
                                                stroke="#3B82F6"
                                                strokeWidth={2}
                                                dot={{ r: 4 }}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                )}
                            </Panel>

                            <Panel title="Prices by vendor">
                                {byVendor.length === 0 ? (
                                    <EmptyState>
                                        No vendor breakdown yet.
                                    </EmptyState>
                                ) : (
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-gray-500">
                                                <th className="pb-2 font-medium">
                                                    Vendor
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Min
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Avg
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Max
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {byVendor.map((row) => (
                                                <tr key={row.vendor ?? 'unknown'}>
                                                    <td className="py-2 text-gray-900">
                                                        {row.vendor ?? '—'}
                                                    </td>
                                                    <td className="py-2 text-right font-medium text-green-700">
                                                        {formatCurrency(
                                                            row.min_price,
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-right text-gray-600">
                                                        {formatCurrency(
                                                            row.avg_price,
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-right text-gray-600">
                                                        {formatCurrency(
                                                            row.max_price,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </Panel>

                            <Panel title="Purchase history">
                                {purchases.length === 0 ? (
                                    <EmptyState>
                                        No linked purchases yet.
                                    </EmptyState>
                                ) : (
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-gray-500">
                                                <th className="pb-2 font-medium">
                                                    Date
                                                </th>
                                                <th className="pb-2 font-medium">
                                                    Vendor
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Qty
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Unit
                                                </th>
                                                <th className="pb-2 text-right font-medium">
                                                    Total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {purchases.map((row) => (
                                                <tr key={row.id}>
                                                    <td className="py-2 text-gray-600">
                                                        {row.receipt_date
                                                            ? new Date(
                                                                  row.receipt_date,
                                                              ).toLocaleDateString()
                                                            : '—'}
                                                    </td>
                                                    <td className="py-2 text-gray-900">
                                                        {row.vendor ?? '—'}
                                                    </td>
                                                    <td className="py-2 text-right text-gray-600">
                                                        {row.quantity}
                                                    </td>
                                                    <td className="py-2 text-right text-gray-600">
                                                        {formatCurrency(
                                                            row.unit_price,
                                                            row.currency,
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-right font-medium text-gray-900">
                                                        {formatCurrency(
                                                            row.total,
                                                            row.currency,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </Panel>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
