import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import DangerButton from '@/Components/DangerButton';
import CancelButton from '@/Components/CancelButton';

export default function Index({ receipts }) {
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [receiptToDelete, setReceiptToDelete] = useState(null);

    const { delete: destroy, processing } = useForm();

    const getStatusBadge = (status) => {
        const badges = {
            pending: 'bg-yellow-100 text-yellow-800',
            processed: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
        };

        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[status]}`}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        );
    };

    const handleDeleteClick = (receipt) => {
        setReceiptToDelete(receipt);
        setShowDeleteModal(true);
    };

    const handleDeleteConfirm = () => {
        if (receiptToDelete) {
            destroy(route('receipts.destroy', receiptToDelete.id), {
                onSuccess: () => {
                    setShowDeleteModal(false);
                    setReceiptToDelete(null);
                },
            });
        }
    };

    const handleDeleteCancel = () => {
        setShowDeleteModal(false);
        setReceiptToDelete(null);
    };

    const formatCurrency = (amount, currency = 'EUR') => {
        if (!amount) return 'N/A';
        return new Intl.NumberFormat('de-DE', {
            style: 'currency',
            currency: currency,
        }).format(amount);
    };

    const formatDate = (date) => {
        return new Date(date).toLocaleDateString('de-DE', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Receipts" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h2 className="text-2xl font-bold">Receipts</h2>
                                <Link href={route('receipts.create')}>
                                    <PrimaryButton>Upload New Receipt</PrimaryButton>
                                </Link>
                            </div>

                            {receipts.data.length === 0 ? (
                                <div className="text-center py-12">
                                    <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">No receipts</h3>
                                    <p className="mt-1 text-sm text-gray-500">Get started by uploading a new receipt.</p>
                                    <div className="mt-6">
                                        <Link href={route('receipts.create')}>
                                            <PrimaryButton>Upload Receipt</PrimaryButton>
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Receipt
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Vendor
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Category
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Amount
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Date
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {receipts.data.map((receipt) => (
                                                <tr key={receipt.id} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="flex items-center">
                                                            <div className="flex-shrink-0 h-10 w-10">
                                                                {receipt.mime?.startsWith('image/') ? (
                                                                    <img
                                                                        className="h-10 w-10 rounded-lg object-cover"
                                                                        src={receipt.public_file_url || receipt.file_url}
                                                                        alt="Receipt"
                                                                    />
                                                                ) : (
                                                                    <div className="h-10 w-10 rounded-lg bg-gray-200 flex items-center justify-center">
                                                                        <svg className="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                        </svg>
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="ml-4">
                                                                <div className="text-sm font-medium text-gray-900">
                                                                    {receipt.original_filename}
                                                                </div>
                                                                <div className="text-sm text-gray-500">
                                                                    {(receipt.file_size / 1024 / 1024).toFixed(2)} MB
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {receipt.vendor || 'N/A'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <div>
                                                            {receipt.category?.name || 'N/A'}
                                                            {receipt.subcategory && (
                                                                <div className="text-xs text-gray-500">
                                                                    {receipt.subcategory.name}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {formatCurrency(receipt.total_amount, receipt.currency)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {getStatusBadge(receipt.status)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {formatDate(receipt.created_at)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div className="flex space-x-2">
                                                            <Link
                                                                href={route('receipts.show', receipt.id)}
                                                                className="text-indigo-600 hover:text-indigo-900"
                                                            >
                                                                View
                                                            </Link>
                                                            <button
                                                                onClick={() => handleDeleteClick(receipt)}
                                                                className="text-red-600 hover:text-red-900"
                                                            >
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>

                                    {/* Pagination */}
                                    {receipts.links && (
                                        <div className="px-6 py-3 border-t border-gray-200">
                                            <nav className="flex items-center justify-between">
                                                <div className="flex-1 flex justify-between sm:hidden">
                                                    {receipts.links.prev && (
                                                        <Link
                                                            href={receipts.links.prev}
                                                            className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                                        >
                                                            Previous
                                                        </Link>
                                                    )}
                                                    {receipts.links.next && (
                                                        <Link
                                                            href={receipts.links.next}
                                                            className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                                        >
                                                            Next
                                                        </Link>
                                                    )}
                                                </div>
                                                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                                    <div>
                                                        <p className="text-sm text-gray-700">
                                                            Showing <span className="font-medium">{receipts.from}</span> to{' '}
                                                            <span className="font-medium">{receipts.to}</span> of{' '}
                                                            <span className="font-medium">{receipts.total}</span> results
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                                            {receipts.links.map((link, index) => (
                                                                <Link
                                                                    key={index}
                                                                    href={link.url || '#'}
                                                                    className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                                                                        link.active
                                                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                                                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                                                    } ${index === 0 ? 'rounded-l-md' : ''} ${
                                                                        index === receipts.links.length - 1 ? 'rounded-r-md' : ''
                                                                    }`}
                                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                                />
                                                            ))}
                                                        </nav>
                                                    </div>
                                                </div>
                                            </nav>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Delete Confirmation Modal */}
            {showDeleteModal && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div className="mt-3 text-center">
                            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 mt-4">Delete Receipt</h3>
                            <div className="mt-2 px-7 py-3">
                                <p className="text-sm text-gray-500">
                                    Are you sure you want to delete "{receiptToDelete?.original_filename}"?
                                    This action cannot be undone and will permanently remove the receipt and its associated data.
                                </p>
                            </div>
                            <div className="items-center px-4 py-3">
                                <div className="flex justify-center space-x-3">
                                    <CancelButton onClick={handleDeleteCancel} />
                                    <DangerButton
                                        onClick={handleDeleteConfirm}
                                        disabled={processing}
                                        className="px-4 py-2 text-sm"
                                    >
                                        {processing ? 'Deleting...' : 'Delete'}
                                    </DangerButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
